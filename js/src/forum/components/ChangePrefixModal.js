import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';
import prefixLabel, { choosablePrefixes, findPrefix, prefixRequired } from '../../common/prefixLabel';

const t = (key, params) => app.translator.trans(`ernestdefoe-rubric.forum.${key}`, params);

/** Pick a discussion's prefix after it was started. Choosing saves it. */
export default class ChangePrefixModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.saving = null;
  }

  className() {
    return 'RubricModal Modal--small';
  }

  title() {
    return t('change_title');
  }

  tagIds() {
    const tags = this.attrs.discussion.tags ? this.attrs.discussion.tags() : null;
    return (tags || []).filter(Boolean).map((tag) => tag.id());
  }

  content() {
    const discussion = this.attrs.discussion;
    const currentId = discussion.attribute('rubricPrefixId');
    const current = findPrefix(currentId);
    const options = choosablePrefixes(this.tagIds());

    // Someone who may rename a discussion but not use staff prefixes can still
    // see the one it has, so it stays listed as the current choice.
    if (current && !options.some((p) => p.id === current.id)) options.unshift(current);

    const option = (prefix) => {
      const id = prefix ? prefix.id : null;
      const active = String(id) === String(currentId ?? null);

      return (
        <li>
          <Button
            className={classList('RubricModal-option', active && 'active')}
            icon={active ? 'fas fa-check' : 'fas fa-fw'}
            loading={this.saving === String(id)}
            disabled={this.saving !== null}
            onclick={() => this.choose(id)}
          >
            {prefix ? prefixLabel(prefix) : <span className="RubricModal-none">{t('no_prefix')}</span>}
          </Button>
        </li>
      );
    };

    return (
      <div className="Modal-body">
        <ul className="RubricModal-options">
          {prefixRequired(this.tagIds()) ? null : option(null)}
          {options.map(option)}
        </ul>
      </div>
    );
  }

  choose(id) {
    if (String(id) === String(this.attrs.discussion.attribute('rubricPrefixId') ?? null)) {
      this.hide();
      return;
    }

    this.saving = String(id);

    this.attrs.discussion
      .save({ rubricPrefixId: id })
      .then(() => {
        this.hide();
        m.redraw();
      })
      .catch(() => {
        this.saving = null;
        m.redraw();
      });
  }
}
