/*
 * Placeholders in a macro's text, filled in as it is inserted. Pure: no Flarum
 * imports, so it is unit-tested as it is.
 *
 * The values are text — names and titles the poster does not control — so
 * each is escaped for the format the editor speaks: HTML for Scribe, Markdown
 * where Markdown is on, nothing for plain BBCode. Mentions are the exception,
 * being syntax by design (and present only when Mentions is on).
 */

export const PLACEHOLDERS = [
  'username',
  'display_name',
  'mention',
  'my_username',
  'my_display_name',
  'discussion_title',
  'discussion_url',
  'forum_name',
  'forum_url',
  'date',
];

const RAW = new Set(['mention']);

export function escapeHtml(text) {
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/** Backslash-escapes the characters Markdown (and Flarum's BBCode brackets) would read as syntax. */
export function escapeMarkdown(text) {
  return String(text).replace(/([\\`*_[\]<>|~#])/g, '\\$1');
}

/**
 * @param {string} content the macro's text
 * @param {Record<string, string|null|undefined>} values by placeholder name
 * @param {'html'|'markdown'|'plain'} format
 */
export function fill(content, values, format) {
  const escape = format === 'html' ? escapeHtml : format === 'markdown' ? escapeMarkdown : (text) => String(text);

  return String(content).replace(/\{([a-z_]+)\}/g, (whole, name) => {
    if (!PLACEHOLDERS.includes(name)) return whole;

    const value = values[name];

    // Without Mentions there is no mention syntax: the name stands in, as text.
    if (name === 'mention' && (value === null || value === undefined)) return fill('{display_name}', values, format);

    if (value === null || value === undefined) return '';

    return RAW.has(name) ? String(value) : escape(value);
  });
}

/** Lower-cased, accent-free, for search. */
export function fold(text) {
  return String(text || '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase();
}

/**
 * The words of a macro's text, for its preview line and for search. Scribe's
 * HTML is read with DOMParser, which runs no scripts and loads no images;
 * everything else is already text.
 */
export function plainText(content, format, parse) {
  const text = format === 'html' && parse ? parse(String(content)) : String(content);

  return text.replace(/\s+/g, ' ').trim();
}
