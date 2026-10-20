import app from 'flarum/forum/app';

/** What the composer's editor reads and writes: Scribe's HTML, Markdown, or plain BBCode text. */
export function editorFormat() {
  const extensions = flarum.extensions;

  if (extensions['ernestdefoe-scribe']) return 'html';
  if (extensions['flarum-markdown'] || extensions['fof-rich-text']) return 'markdown';

  return 'plain';
}

/** The words of an HTML string, read without rendering it: DOMParser runs no scripts and loads nothing. */
export function htmlText(html) {
  // A space after each block, so paragraphs and list items do not run together.
  const spaced = String(html).replace(/<\/(p|li|h[1-6]|blockquote|pre|div|tr|td|th)>|<br\s*\/?>/gi, '$& ');

  return new DOMParser().parseFromString(spaced, 'text/html').body.textContent || '';
}

/** Placeholder values for the composer as it is now. The discussion parts are empty when starting one. */
export function placeholderValues() {
  const attrs = (app.composer.body && app.composer.body.attrs) || {};
  const discussion = attrs.discussion || (attrs.post && attrs.post.discussion && attrs.post.discussion()) || null;
  const starter = (discussion && discussion.user && discussion.user()) || null;
  const me = app.session.user;
  // flarum/mentions' own clean-up: a display name must not end the mention early.
  const mentionName = starter && String(starter.displayName()).replace(/"#[a-z]{0,3}[0-9]+/, '_');

  return {
    username: starter && starter.username(),
    display_name: starter && starter.displayName(),
    mention: starter && flarum.extensions['flarum-mentions'] ? `@"${mentionName}"#${starter.id()}` : null,
    my_username: me && me.username(),
    my_display_name: me && me.displayName(),
    discussion_title: discussion && discussion.title(),
    discussion_url: discussion && discussion.id() ? app.forum.attribute('baseOrigin') + app.route.discussion(discussion) : null,
    forum_name: app.forum.attribute('title'),
    forum_url: app.forum.attribute('baseUrl'),
    date: dayjs().format('LL'),
  };
}
