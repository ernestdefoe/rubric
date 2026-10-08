import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import Stream from 'flarum/common/utils/Stream';
import prefixLabel from '../../common/prefixLabel';

const t = (key, params) => app.translator.trans(`ernestdefoe-rubric.admin.${key}`, params);
const SETTING = 'ernestdefoe-rubric.required_tags';

const SWATCHES = ['#dc2626', '#ea580c', '#d97706', '#16a34a', '#0d9488', '#2563eb', '#7c3aed', '#db2777', '#475569', '#111827'];

const slugify = (s) =>
  String(s || '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const blank = () => ({ id: null, name: '', slug: '', color: '#2563eb', icon: '', staffOnly: false, tagIds: [] });

/**
 * The whole extension's settings on one page: the prefixes (drag to order,
 * edit in place, live preview) and where a prefix is required.
 *
 * 🚨 A page of its own, not registered settings. The prefixes are rows in a
 * table with their own endpoints; core's Save button would save none of them.
 */
export default class RubricPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.prefixes = (app.forum.attribute('rubricPrefixes') || []).slice();
    this.tags = null;
    this.editing = null; // a draft object, or null
    this.busy = false;

    let required = [];
    try {
      required = JSON.parse(app.data.settings[SETTING] || '[]');
    } catch (e) {}
    this.required = Stream((Array.isArray(required) ? required : []).map(String));
    this.requiredDirty = false;
    this.savingRequired = false;

    this.request('GET').then(() => m.redraw());

    if (this.tagsEnabled()) {
      app
        .request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/tags' })
        .then((res) => {
          this.tags = (res.data || [])
            .map((d) => ({
              id: String(d.id),
              name: d.attributes.name,
              color: d.attributes.color,
              position: d.attributes.position,
              parent: d.relationships?.parent?.data?.id,
            }))
            .sort((a, b) => (a.position ?? 999) - (b.position ?? 999) || a.name.localeCompare(b.name));
          m.redraw();
        })
        .catch(() => {
          this.tags = [];
          m.redraw();
        });
    } else {
      this.tags = [];
    }
  }

  tagsEnabled() {
    return 'flarum-tags' in (app.data.extensions || {}) && !!app.data.extensions['flarum-tags'];
  }

  request(method, path = '', body) {
    this.busy = true;

    return app
      .request({ method, url: `${app.forum.attribute('apiUrl')}/rubric/prefixes${path}`, body })
      .then((res) => {
        this.prefixes = res.data || [];
        // The forum payload the admin holds is used for previews elsewhere.
        app.forum.pushAttributes({ rubricPrefixes: this.prefixes });
        return res;
      })
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }

  content() {
    return (
      <div className="ExtensionPage-settings RubricAdmin">
        <div className="container">
          <section className="RubricAdmin-section">
            <h3>{t('prefixes_heading')}</h3>
            <p className="helpText">{t('prefixes_help')}</p>

            {this.prefixes.length ? (
              <ol className="RubricAdmin-list" oncreate={(v) => this.sortable(v.dom)}>
                {this.prefixes.map((p) => this.row(p))}
              </ol>
            ) : (
              <p className="RubricAdmin-empty">{t('empty')}</p>
            )}

            {this.editing && this.editing.id === null ? (
              this.editor()
            ) : (
              <Button
                className="Button Button--primary RubricAdmin-add"
                icon="fas fa-plus"
                onclick={() => this.open(blank())}
                disabled={!!this.editing}
              >
                {t('add_button')}
              </Button>
            )}
          </section>

          {this.tagsEnabled() ? this.requiredSection() : null}
        </div>
      </div>
    );
  }

  row(p) {
    if (this.editing && this.editing.id === p.id) {
      return (
        <li key={p.id} data-id={p.id} className="RubricAdmin-row RubricAdmin-row--editing">
          {this.editor()}
        </li>
      );
    }

    return (
      <li key={p.id} data-id={p.id} className="RubricAdmin-row">
        <span className="RubricAdmin-handle" title={extractText(t('drag_tooltip'))}>
          {<Icon name="fas fa-grip-vertical" />}
        </span>
        <span className="RubricAdmin-preview">{prefixLabel(p)}</span>
        <span className="RubricAdmin-meta">
          <code>?prefix={p.slug}</code>
          {p.staffOnly ? (
            <span className="RubricAdmin-badge">
              {<Icon name="fas fa-shield-alt" />} {t('staff_only_badge')}
            </span>
          ) : null}
          <span className="RubricAdmin-where">{this.whereText(p)}</span>
        </span>
        <span className="RubricAdmin-actions">
          <Button
            className="Button Button--icon Button--link"
            icon="fas fa-pencil-alt"
            aria-label={extractText(t('edit_button'))}
            onclick={() => this.open({ ...p, icon: p.icon || '', tagIds: (p.tagIds || []).map(String) })}
            disabled={!!this.editing}
          />
          <Button
            className="Button Button--icon Button--link RubricAdmin-delete"
            icon="fas fa-trash-alt"
            aria-label={extractText(t('delete_button'))}
            onclick={() => this.remove(p)}
            disabled={!!this.editing || this.busy}
          />
        </span>
      </li>
    );
  }

  open(draft) {
    this.tagQuery = { ...(this.tagQuery || {}), editor: '' };
    this.editing = draft;
  }

  whereText(p) {
    if (!this.tagsEnabled() || !(p.tagIds || []).length) return t('everywhere');
    const names = (p.tagIds || [])
      .map((id) => (this.tags || []).find((tag) => tag.id === String(id)))
      .filter(Boolean)
      .map((tag) => tag.name);
    return t('only_in', { tags: names.join(', ') || '…' });
  }

  editor() {
    const d = this.editing;
    const preview = {
      ...d,
      name: d.name.trim() || extractText(t('name_placeholder')),
      color: /^#[0-9a-f]{6}$/i.test(d.color) ? d.color : '#2563eb',
      icon: d.icon.trim(),
    };

    return (
      <form
        className="RubricAdmin-editor"
        onsubmit={(e) => {
          e.preventDefault();
          this.save();
        }}
      >
        <div className="RubricAdmin-previewBar">
          <span className="RubricAdmin-previewLabel">{t('preview')}</span>
          {prefixLabel(preview)}
          <span className="RubricAdmin-previewTitle">{t('preview_title')}</span>
        </div>

        <div className="RubricAdmin-fields">
          <div className="Form-group">
            <label>{t('name_label')}</label>
            <input
              className="FormControl"
              maxlength="60"
              placeholder={extractText(t('name_placeholder'))}
              value={d.name}
              oncreate={(v) => v.dom.focus()}
              oninput={(e) => {
                const autoSlug = !d.slug || d.slug === slugify(d.name);
                d.name = e.target.value;
                if (autoSlug && d.id === null) d.slug = slugify(d.name);
              }}
            />
          </div>

          <div className="Form-group">
            <label>{t('slug_label')}</label>
            <input
              className="FormControl"
              maxlength="80"
              value={d.slug}
              placeholder={slugify(d.name)}
              oninput={(e) => (d.slug = slugify(e.target.value) || e.target.value.toLowerCase())}
            />
            <p className="helpText">{t('slug_help')}</p>
          </div>

          <div className="Form-group">
            <label>{t('color_label')}</label>
            <div className="RubricAdmin-color">
              <input
                type="color"
                className="RubricAdmin-colorInput"
                value={preview.color}
                oninput={(e) => (d.color = e.target.value)}
                aria-label={extractText(t('color_label'))}
              />
              <input className="FormControl RubricAdmin-colorText" maxlength="7" value={d.color} oninput={(e) => (d.color = e.target.value.trim())} />
              <span className="RubricAdmin-swatches">
                {SWATCHES.map((c) => (
                  <button
                    type="button"
                    className={classList('RubricAdmin-swatch', d.color.toLowerCase() === c && 'active')}
                    style={{ background: c }}
                    aria-label={c}
                    onclick={() => (d.color = c)}
                  />
                ))}
              </span>
            </div>
          </div>

          <div className="Form-group">
            <label>{t('icon_label')}</label>
            <input className="FormControl" maxlength="100" placeholder="fas fa-bullhorn" value={d.icon} oninput={(e) => (d.icon = e.target.value)} />
            <p className="helpText">{t('icon_help')}</p>
          </div>

          {this.tagsEnabled() ? (
            <div className="Form-group">
              <label>{t('tags_label')}</label>
              {this.tagPicker(d.tagIds, (ids) => (d.tagIds = ids), 'editor')}
              <p className="helpText">{t('tags_help')}</p>
            </div>
          ) : null}

          <div className="Form-group">
            <Switch state={!!d.staffOnly} onchange={(v) => (d.staffOnly = v)}>
              {t('staff_only_label')}
            </Switch>
            <p className="helpText">{t('staff_only_help')}</p>
          </div>
        </div>

        <div className="RubricAdmin-editorActions">
          <Button type="submit" className="Button Button--primary" loading={this.busy} disabled={!d.name.trim()}>
            {d.id === null ? t('create_button') : t('save_button')}
          </Button>
          <Button className="Button" onclick={() => (this.editing = null)} disabled={this.busy}>
            {t('cancel_button')}
          </Button>
        </div>
      </form>
    );
  }

  /**
   * Choosing tags. A forum can have hundreds, so past a handful the chosen
   * ones show as chips and the rest are found by typing.
   */
  tagPicker(selected, onchange, key) {
    if (this.tags === null) return <p className="helpText">{t('loading_tags')}</p>;
    if (!this.tags.length) return <p className="helpText">{t('no_tags')}</p>;

    const ids = selected.map(String);
    const chip = (tag) => {
      const on = ids.includes(tag.id);
      return (
        <button
          type="button"
          className={classList('RubricAdmin-tag', on && 'active')}
          style={{ '--tag-color': tag.color || 'var(--muted-color)' }}
          aria-pressed={on ? 'true' : 'false'}
          onclick={() => onchange(on ? ids.filter((id) => id !== tag.id) : [...ids, tag.id])}
        >
          {on ? <Icon name="fas fa-check" /> : <span className="RubricAdmin-tagDot" />}
          {tag.name}
          {on && this.tags.length > 24 ? <Icon name="fas fa-times" className="RubricAdmin-tagRemove" /> : null}
        </button>
      );
    };

    if (this.tags.length <= 24) return <div className="RubricAdmin-tags">{this.tags.map(chip)}</div>;

    this.tagQuery = this.tagQuery || {};
    const q = (this.tagQuery[key] || '').trim().toLowerCase();
    const chosen = this.tags.filter((tag) => ids.includes(tag.id));
    const matches = q ? this.tags.filter((tag) => !ids.includes(tag.id) && tag.name.toLowerCase().includes(q)).slice(0, 12) : [];

    return (
      <div className="RubricAdmin-tagPicker">
        {chosen.length ? <div className="RubricAdmin-tags">{chosen.map(chip)}</div> : null}
        <input
          className="FormControl RubricAdmin-tagSearch"
          type="search"
          placeholder={extractText(t('tag_search_placeholder'))}
          value={this.tagQuery[key] || ''}
          oninput={(e) => (this.tagQuery[key] = e.target.value)}
        />
        {q ? (
          <div className="RubricAdmin-tags RubricAdmin-tagMatches">
            {matches.length ? matches.map(chip) : <span className="helpText">{t('tag_search_none')}</span>}
          </div>
        ) : null}
      </div>
    );
  }

  requiredSection() {
    return (
      <section className="RubricAdmin-section">
        <h3>{t('required_heading')}</h3>
        <p className="helpText">{t('required_help')}</p>
        {this.tagPicker(
          this.required(),
          (ids) => {
            this.required(ids);
            this.requiredDirty = true;
          },
          'required'
        )}
        <Button
          className="Button Button--primary RubricAdmin-saveRequired"
          loading={this.savingRequired}
          disabled={!this.requiredDirty}
          onclick={() => this.saveRequired()}
        >
          {t('save_required_button')}
        </Button>
      </section>
    );
  }

  saveRequired() {
    this.savingRequired = true;
    const value = JSON.stringify(this.required().map(Number));

    app
      .request({ method: 'POST', url: app.forum.attribute('apiUrl') + '/settings', body: { [SETTING]: value } })
      .then(() => {
        app.data.settings[SETTING] = value;
        this.requiredDirty = false;
        app.alerts.show({ type: 'success' }, t('saved'));
      })
      .finally(() => {
        this.savingRequired = false;
        m.redraw();
      });
  }

  save() {
    const d = this.editing;
    const body = {
      name: d.name.trim(),
      slug: d.slug.trim(),
      color: d.color.trim(),
      icon: d.icon.trim(),
      staffOnly: !!d.staffOnly,
      tagIds: (d.tagIds || []).map(Number),
    };

    (d.id === null ? this.request('POST', '', body) : this.request('PATCH', `/${d.id}`, body)).then(() => {
      this.editing = null;
      app.alerts.show({ type: 'success' }, t('saved'));
    });
  }

  remove(p) {
    if (!confirm(extractText(t('delete_confirm', { prefix: p.name })))) return;
    this.request('DELETE', `/${p.id}`);
  }

  /**
   * Drag to reorder.
   *
   * 🚨 The DOM move is undone before the rows are reordered: Sortable moves
   * the element itself, and Mithril must make the move from the data or the
   * two disagree about which node is which.
   */
  sortable(list) {
    // Core's own lazily loaded copy of sortablejs (~120 KB), not a second one
    // bundled into this extension's admin.js.
    import('flarum/admin/utils/loadSortable').then(({ default: Sortable }) => this.attachSortable(Sortable, list));
  }

  attachSortable(Sortable, list) {
    if (!list.isConnected) return;
    Sortable.create(list, {
      handle: '.RubricAdmin-handle',
      animation: 150,
      onEnd: (e) => {
        const { oldIndex, newIndex, item, from } = e;
        if (oldIndex === newIndex) return;

        from.removeChild(item);
        from.insertBefore(item, from.children[oldIndex] || null);

        const [moved] = this.prefixes.splice(oldIndex, 1);
        this.prefixes.splice(newIndex, 0, moved);
        m.redraw();

        this.request('POST', '/order', { order: this.prefixes.map((p) => p.id) });
      },
    });
  }
}
