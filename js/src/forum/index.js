import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import Icon from 'flarum/common/components/Icon';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import DiscussionList from 'flarum/forum/components/DiscussionList';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import GlobalSearchState from 'flarum/forum/states/GlobalSearchState';
import ChangePrefixModal from './components/ChangePrefixModal';
import prefixLabel, { choosablePrefixes, findPrefix, findPrefixBySlug, prefixRequired } from '../common/prefixLabel';

const t = (key, params) => app.translator.trans(`ernestdefoe-rubric.forum.${key}`, params);


export { default as extend } from './extend';

/** Each part is guarded on its own: one failing must not take the rest, or anyone else's initializer, with it. */
const guard = (name, fn) => {
  try {
    fn();
  } catch (e) {
    console.error(`[rubric] ${name} failed`, e);
  }
};

/** The index, filtered to one prefix, keeping whatever else (tag, sort, search) is in force. */
function filterTo(slug) {
  const onIndex = app.current && app.current.get('routeName') && ['index', 'tag'].includes(app.current.get('routeName'));
  const routeName = onIndex ? app.current.get('routeName') : 'index';
  const params = onIndex ? app.search.state.params() : {};

  if (slug) params.prefix = slug;
  else delete params.prefix;

  Object.keys(params).forEach((k) => (params[k] === undefined || params[k] === null || params[k] === '') && delete params[k]);
  if (params.filter && !Object.keys(params.filter).length) delete params.filter;

  m.route.set(app.route(routeName, params));
}

function clickableLabel(prefix, className) {
  return prefixLabel(prefix, {
    className,
    title: extractTitle(prefix),
    onclick: (e) => {
      e.preventDefault();
      e.stopPropagation();
      filterTo(prefix.slug);
    },
  });
}

function extractTitle(prefix) {
  return app.translator.trans('ernestdefoe-rubric.forum.filter_tooltip', { prefix: prefix.name }, true);
}

function tagIdsOf(tags) {
  return (tags || []).filter(Boolean).map((tag) => tag.id());
}

/** Find the first vnode in a tree whose className contains `cls`. */
function findVnode(vnode, cls) {
  if (!vnode || typeof vnode !== 'object') return null;
  if (Array.isArray(vnode)) {
    for (const child of vnode) {
      const hit = findVnode(child, cls);
      if (hit) return hit;
    }
    return null;
  }
  const c = vnode.attrs && (vnode.attrs.className || vnode.attrs.class);
  if (typeof c === 'string' && c.split(/\s+/).includes(cls)) return vnode;
  return findVnode(vnode.children, cls);
}

/**
 * Put a label at the start of a heading vnode, keeping what was in it.
 * Rebuilt through m() so the new children are normalised the way Mithril
 * expects (a heading with plain text holds it in `.text`, not `.children`).
 */
function prependTo(heading, label) {
  if (!heading || !label) return;

  const existing = heading.text !== undefined && heading.text !== null ? heading.text : heading.children || [];
  const fresh = m(heading.tag, heading.attrs, [label, existing]);

  heading.text = fresh.text;
  heading.children = fresh.children;
}

app.initializers.add('ernestdefoe-rubric', () => {
  // Labels in the discussion list.
  guard('list', () => {
    extend('flarum/forum/components/DiscussionListItem', 'mainView', function (vdom) {
      const prefix = findPrefix(this.attrs.discussion.attribute('rubricPrefixId'));
      if (!prefix) return;

      prependTo(findVnode(vdom, 'DiscussionListItem-title'), clickableLabel(prefix, 'RubricLabel--list'));
    });
  });

  // The label on the discussion page's own title.
  guard('hero', () => {
    extend('flarum/forum/components/DiscussionHero', 'items', function (items) {
      const discussion = this.attrs.discussion;
      const prefix = findPrefix(discussion.attribute('rubricPrefixId'));
      if (!prefix || !items.has('title')) return;

      items.setContent(
        'title',
        <h1 className="DiscussionHero-title">
          {clickableLabel(prefix, 'RubricLabel--hero')}
          {discussion.title()}
        </h1>
      );
    });
  });

  // The picker in the composer, beside the tags.
  guard('composer', () => {
    extend('flarum/forum/components/DiscussionComposer', 'headerItems', function (items) {
      const fields = this.composer.fields;
      const tagIds = tagIdsOf(fields.tags);
      const options = choosablePrefixes(tagIds);

      // A prefix chosen before the tags changed may not fit the new ones.
      if (fields.rubricPrefixId && !options.some((p) => String(p.id) === String(fields.rubricPrefixId))) {
        fields.rubricPrefixId = null;
      }

      if (!options.length) return;

      const current = findPrefix(fields.rubricPrefixId);
      const required = prefixRequired(tagIds);

      items.add(
        'rubricPrefix',
        <Dropdown
          className="RubricPicker"
          buttonClassName="Button Button--ua-reset RubricPicker-button"
          accessibleToggleLabel={t('choose_prefix')}
          label={
            current ? (
              prefixLabel(current)
            ) : (
              <span className={'RubricLabel RubricLabel--empty' + (required ? ' RubricLabel--required' : '')}>
                {required ? t('choose_prefix_required') : t('choose_prefix')}
                <Icon name="fas fa-caret-down" className="RubricLabel-caret" />
              </span>
            )
          }
        >
          {[
            required ? null : (
              <Button icon={!current ? 'fas fa-check' : 'fas fa-fw'} onclick={() => (fields.rubricPrefixId = null)}>
                {t('no_prefix')}
              </Button>
            ),
            ...options.map((p) => (
              <Button
                icon={current && current.id === p.id ? 'fas fa-check' : 'fas fa-fw'}
                className="RubricPicker-option"
                onclick={() => (fields.rubricPrefixId = p.id)}
              >
                {prefixLabel(p)}
              </Button>
            )),
          ].filter(Boolean)}
        </Dropdown>,
        // Just after the tag selector (10).
        5
      );
    });

    extend('flarum/forum/components/DiscussionComposer', 'data', function (data) {
      const id = this.composer.fields.rubricPrefixId;
      if (id) {
        data.rubricPrefixId = Number(id);
      }
    });
  });

  // "Change prefix" for anyone who may rename the discussion.
  guard('controls', () => {
    extend(DiscussionControls, 'moderationControls', function (items, discussion) {
      if (!discussion.canRename || !discussion.canRename()) return;

      const tags = discussion.tags ? discussion.tags() : null;
      const hasCurrent = !!findPrefix(discussion.attribute('rubricPrefixId'));
      if (!hasCurrent && !choosablePrefixes(tagIdsOf(tags)).length) return;

      items.add(
        'rubricPrefix',
        <Button icon="fas fa-bookmark" onclick={() => app.modal.show(ChangePrefixModal, { discussion })}>
          {t('change_button')}
        </Button>,
        // Just after Rename (100).
        95
      );
    });
  });

  // Filtering: /?prefix=rumor is carried like any other list parameter and sent as filter[prefix].
  guard('filter', () => {
    extend(GlobalSearchState.prototype, 'params', function (params) {
      const slug = m.route.param('prefix');
      if (slug) params.prefix = slug;
    });

    extend(DiscussionListState.prototype, 'requestParams', function (params) {
      if (this.params.prefix) {
        params.filter = params.filter || {};
        params.filter.prefix = this.params.prefix;
      }
    });

    // The server preloads the first page of the list without knowing about
    // ?prefix=, so on a fresh load that page is the unfiltered one. Fetch
    // instead when a prefix is asked for.
    override(DiscussionListState.prototype, 'loadPage', function (original, ...args) {
      if (this.params.prefix && app.data.apiDocument) app.data.apiDocument = null;
      return original(...args);
    });

    // A theme may swap the home page's list for something else (Bespoke's
    // category index does). Filtered to a prefix, the list is the point, so
    // put it back.
    extend('flarum/forum/components/IndexPage', 'contentItems', function (items) {
      if (!app.search.state.params().prefix || items.has('discussionList')) return;

      items.has('bespoke-categories') && items.remove('bespoke-categories');
      items.add('discussionList', <DiscussionList state={app.discussions} />, 90);
    });

    // The removable "Prefix: Rumor" chip while filtering. Its own row above
    // the list rather than a toolbar item: themes hide or rebuild the toolbar
    // (Bespoke does), and the chip is the only way back out of the filter.
    extend('flarum/forum/components/IndexPage', 'contentItems', function (items) {
      const slug = app.search.state.params().prefix;
      if (!slug) return;

      const prefix = findPrefixBySlug(slug);

      items.add(
        'rubricFilter',
        <div className="RubricFilterBar">
          <span className="RubricChip">
            <span className="RubricChip-text">{t('filter_chip')}</span>
            {prefix ? prefixLabel(prefix) : <span className="RubricLabel RubricLabel--empty">{slug}</span>}
            <Button
              className="Button Button--icon Button--link RubricChip-remove"
              icon="fas fa-times"
              aria-label={app.translator.trans('ernestdefoe-rubric.forum.filter_clear', {}, true)}
              onclick={() => filterTo(null)}
            />
          </span>
        </div>,
        95
      );
    });
  });
});
