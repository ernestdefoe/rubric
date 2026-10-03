import app from 'flarum/common/app';
import icon from 'flarum/common/helpers/icon';
import classList from 'flarum/common/utils/classList';

/**
 * Dark or light text for a background colour, from its relative luminance
 * (WCAG), so a label is readable whatever colour an admin picks.
 */
export function textColorFor(hex) {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
  if (!m) return '#fff';

  const n = parseInt(m[1], 16);
  const channel = (c) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  };
  const L = 0.2126 * channel((n >> 16) & 255) + 0.7152 * channel((n >> 8) & 255) + 0.0722 * channel(n & 255);

  // The crossover where black and white text have equal contrast.
  return L > 0.179 ? '#111827' : '#fff';
}

/**
 * The label itself. `prefix` is a plain object from the forum payload
 * ({ id, name, slug, color, icon, staffOnly, tagIds }).
 */
export default function prefixLabel(prefix, attrs = {}) {
  if (!prefix) return null;

  const { className, onclick, title, ...rest } = attrs;

  return (
    <span
      className={classList('RubricLabel', onclick && 'RubricLabel--link', className)}
      style={{ '--rubric-color': prefix.color, '--rubric-text': textColorFor(prefix.color) }}
      onclick={onclick}
      role={onclick ? 'link' : undefined}
      tabindex={onclick ? 0 : undefined}
      title={title}
      onkeydown={onclick ? (e) => (e.key === 'Enter' || e.key === ' ') && onclick(e) : undefined}
      {...rest}
    >
      {prefix.icon ? icon(prefix.icon, { className: 'RubricLabel-icon' }) : null}
      <span className="RubricLabel-text">{prefix.name}</span>
    </span>
  );
}

/** The prefix list shipped with the forum payload. Read lazily, never at boot. */
export function allPrefixes() {
  return (app.forum && app.forum.attribute('rubricPrefixes')) || [];
}

export function findPrefix(id) {
  if (id === null || id === undefined) return null;
  return allPrefixes().find((p) => String(p.id) === String(id)) || null;
}

export function findPrefixBySlug(slug) {
  if (!slug) return null;
  const s = String(slug).toLowerCase();
  return allPrefixes().find((p) => p.slug === s) || null;
}

function tagsEnabled() {
  return typeof flarum !== 'undefined' && flarum.extensions && 'flarum-tags' in flarum.extensions;
}

/** Same rule as the server's Prefix::allowedIn(). */
export function allowedIn(prefix, tagIds) {
  if (!tagsEnabled()) return true;
  const own = (prefix.tagIds || []).map(String);
  if (!own.length) return true;
  return (tagIds || []).some((id) => own.includes(String(id)));
}

/** The prefixes this member may pick for a discussion in these tags. */
export function choosablePrefixes(tagIds) {
  const mayStaff = !!(app.forum && app.forum.attribute('canUseStaffPrefixes'));
  return allPrefixes().filter((p) => (!p.staffOnly || mayStaff) && allowedIn(p, tagIds));
}

/** Whether a new discussion in these tags must have a prefix. */
export function prefixRequired(tagIds) {
  if (!tagsEnabled()) return false;
  const required = ((app.forum && app.forum.attribute('rubricRequiredTags')) || []).map(String);
  return (tagIds || []).some((id) => required.includes(String(id))) && choosablePrefixes(tagIds).length > 0;
}
