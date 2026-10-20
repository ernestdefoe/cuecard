# Cue Cards

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/ernestdefoe/cuecard.svg)](https://packagist.org/packages/ernestdefoe/cuecard)

Saved replies for [Flarum 2](https://flarum.org). Staff keep the answers they type every day — "please search first", "moved to the right category", "solved, closing this" — and insert them into the composer in two keystrokes. A macro can fill in the member's name and the discussion title, and it can lock the discussion, tag it or mark the reply as the best answer as the reply posts.

![The macro picker](https://raw.githubusercontent.com/ernestdefoe/cuecard/main/.github/screenshots/picker.jpg)

## Features

- **A button in the composer.** It opens a picker with every macro you may use: search by title or text, choose with ↑ ↓ and press Enter. The macro goes in at the cursor, in a reply, a new discussion or an edit.
- **Shared and personal macros.** Shared macros are written by staff and offered to the groups chosen for each one (Members for everyone allowed). Personal macros belong to whoever saved them and nobody else sees them.
- **Written in your forum's own editor.** A macro is written in the same editor as a post — plain Markdown/BBCode, [FoF Rich Text](https://github.com/FriendsOfFlarum/rich-text) or [Scribe](https://github.com/ernestdefoe/scribe) — so formatting, links and images come out exactly as typed.
- **Save what you just wrote.** "Save this text as a macro" turns the composer's contents into a new macro.
- **Placeholders**, filled in as the macro is inserted:

  | Placeholder | Becomes |
  |---|---|
  | `{username}`, `{display_name}` | the discussion starter's username or display name |
  | `{mention}` | a mention of the discussion starter |
  | `{my_username}`, `{my_display_name}` | yours |
  | `{discussion_title}`, `{discussion_url}` | the discussion's title and link |
  | `{forum_name}`, `{forum_url}` | the forum's name and address |
  | `{date}` | today's date, in the reader's language |

  In a new discussion the discussion and starter placeholders are left empty.
- **Actions when the reply posts** (each optional): lock the discussion ([Lock](https://github.com/flarum/lock)), add tags or replace them ([Tags](https://github.com/flarum/tags)), mark the reply as the best answer ([FoF Best Answer](https://github.com/FriendsOfFlarum/best-answer)). After inserting, the composer shows what will happen, and each action can be dropped before posting.
- **Your forum's rules decide.** An action is carried out as if the poster had clicked Lock, Edit Tags or Best Answer themselves: the same permissions, tag scopes, tag limits and Best Answer settings apply. Anything the poster is not allowed to do is skipped and they are told; the reply is posted regardless.
- **Light.** Nothing about macros is added to page loads. The picker and the macros load the first time the button is pressed.

![Writing a macro](https://raw.githubusercontent.com/ernestdefoe/cuecard/main/.github/screenshots/editor.jpg)

![Actions waiting in the composer](https://raw.githubusercontent.com/ernestdefoe/cuecard/main/.github/screenshots/actions.jpg)

## Installation

```sh
composer require ernestdefoe/cuecard
php flarum migrate
php flarum cache:clear
```

## Updating

```sh
composer update ernestdefoe/cuecard
php flarum migrate
php flarum cache:clear
```

## Permissions

| Permission | Default | What it allows |
|---|---|---|
| Insert global macros | Moderators | Using the shared macros offered to your groups |
| Save personal macros | Moderators | Saving, editing and deleting your own macros (up to 100) |
| Create and edit global macros | Admins | Managing shared macros and who they are offered to |

Give **Save personal macros** to Members if you want everyone to have their own saved replies.

## Notes

- Marking your own reply as the best answer needs FoF Best Answer's "select own post" permission, and a tag with best answers switched on, as it does by hand.
- A macro is stored in the format of the editor it was written in. If the forum later switches between Markdown and Scribe, edit the macros once in the new editor.

## Links

- [Source](https://github.com/ernestdefoe/cuecard)
- [Report an issue](https://github.com/ernestdefoe/cuecard/issues)
- ☕ [Buy me a coffee](https://buymeacoffee.com/ernestdefoe)
