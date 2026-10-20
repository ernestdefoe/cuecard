import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fill, escapeMarkdown, escapeHtml, fold, plainText } from '../src/forum/placeholders.js';

const values = {
  username: 'jo_doe',
  display_name: 'Jo <b>Doe</b>',
  mention: '@"Jo Doe"#5',
  discussion_title: 'Why *this* & [that]?',
  forum_name: 'Forum',
};

test('fills known placeholders and leaves unknown ones alone', () => {
  assert.equal(fill('Hi {username}, see {nope} on {forum_name}.', values, 'plain'), 'Hi jo_doe, see {nope} on Forum.');
});

test('missing values become empty, not "undefined"', () => {
  assert.equal(fill('[{discussion_url}]', values, 'plain'), '[]');
});

test('escapes values for Markdown', () => {
  assert.equal(fill('{discussion_title}', values, 'markdown'), 'Why \\*this\\* & \\[that\\]?');
  assert.equal(fill('{username}', values, 'markdown'), 'jo\\_doe');
});

test('escapes values for HTML (Scribe)', () => {
  assert.equal(fill('<p>{display_name}</p>', values, 'html'), '<p>Jo &lt;b&gt;Doe&lt;/b&gt;</p>');
  assert.equal(fill('{discussion_title}', values, 'html'), 'Why *this* &amp; [that]?');
});

test('a mention stays syntax in every format', () => {
  for (const format of ['plain', 'markdown', 'html']) {
    assert.equal(fill('{mention}', values, format), '@"Jo Doe"#5');
  }
});

test('a value cannot smuggle in another placeholder', () => {
  assert.equal(fill('{username}', { username: '{forum_name}', forum_name: 'X' }, 'plain'), '{forum_name}');
});

test('escape helpers', () => {
  assert.equal(escapeMarkdown('a\\b'), 'a\\\\b');
  assert.equal(escapeHtml(`"'`), '&quot;&#39;');
});

test('fold ignores case and accents', () => {
  assert.equal(fold('Réponse RAPIDE'), 'reponse rapide');
});

test('plainText collapses whitespace and reads HTML through the parser it is given', () => {
  assert.equal(plainText('a\n\n  b', 'markdown'), 'a b');
  assert.equal(
    plainText('<p>x</p>', 'html', () => 'x'),
    'x'
  );
});

test('without Mentions, {mention} is the escaped display name', () => {
  const noMention = { ...values, mention: null };
  assert.equal(fill('{mention}', noMention, 'html'), 'Jo &lt;b&gt;Doe&lt;/b&gt;');
  assert.equal(fill('{mention}', { mention: null }, 'plain'), '');
});
