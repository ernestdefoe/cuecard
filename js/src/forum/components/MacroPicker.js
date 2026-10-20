import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Tooltip from 'flarum/common/components/Tooltip';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';
import { fill, fold, plainText } from '../placeholders';
import { allowedActions, addActions } from '../actions';
import { editorFormat, htmlText, placeholderValues } from '../context';
import MacroEditorModal from './MacroEditorModal';

const t = (key, params) => app.translator.trans('ernestdefoe-cuecard.forum.picker.' + key, params);

// Macros are fetched once per page, the first time the picker opens.
let loading = null;

function load() {
  loading ??= app.store.find('cuecard-macros', app.forum.attribute('cuecardManage') ? { include: 'groups' } : {}).catch((error) => {
    loading = null;
    throw error;
  });

  return loading;
}

// Preview lines, by text: Scribe's HTML is parsed once per macro text, not on every redraw.
const previews = new Map();

function preview(content, format) {
  if (!previews.has(content)) previews.set(content, plainText(content, format, htmlText));

  return previews.get(content);
}

const byTitle = (a, b) => a.title().localeCompare(b.title(), undefined, { sensitivity: 'base', numeric: true });

/**
 * Pick a macro to insert at the cursor: search, arrow keys, Enter. Staff and
 * anyone allowed to save their own can also add, edit and delete from here.
 */
export default class MacroPicker extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.query = '';
    this.index = 0;
    this.loaded = false;
    this.format = editorFormat();

    load()
      .then(() => (this.loaded = true))
      .catch(() => this.hide())
      .finally(() => m.redraw());
  }

  className() {
    return 'CuecardPicker Modal--small';
  }

  title() {
    return t('title');
  }

  /** Every macro this person may insert, sorted, then narrowed by the search. */
  macros() {
    const all = app.store.all('cuecard-macros');
    const words = fold(this.query).split(/\s+/).filter(Boolean);
    const matches = (macro) => {
      if (!words.length) return true;
      const haystack = fold(macro.title() + ' ' + preview(macro.content(), this.format));
      return words.every((word) => haystack.includes(word));
    };

    const shared = all.filter((macro) => macro.isGlobal() && matches(macro)).sort(byTitle);
    const yours = all.filter((macro) => !macro.isGlobal() && matches(macro)).sort(byTitle);

    return { shared, yours, flat: [...shared, ...yours], total: all.length };
  }

  /** The search box takes the keyboard at once (and again once the modal has animated in): type, arrow, Enter. */
  oncreate(vnode) {
    super.oncreate(vnode);
    this.onready();
  }

  onready() {
    this.$('.CuecardPicker-search input').trigger('focus');
  }

  content() {
    const search = (
      <div className="Form-group CuecardPicker-search">
        <input
          className="FormControl"
          type="search"
          placeholder={t('search_placeholder')}
          aria-label={t('search_placeholder')}
          value={this.query}
          oninput={(e) => {
            this.query = e.target.value;
            this.index = 0;
          }}
          onkeydown={this.onkeydown.bind(this)}
        />
      </div>
    );

    if (!this.loaded) {
      return (
        <div className="Modal-body">
          {search}
          <LoadingIndicator />
        </div>
      );
    }

    const { shared, yours, flat, total } = this.macros();

    if (this.index >= flat.length) this.index = Math.max(0, flat.length - 1);

    const canSave = app.forum.attribute('cuecardSavePersonal') || app.forum.attribute('cuecardManage');
    const composerText = app.composer.fields && app.composer.fields.content ? app.composer.fields.content() : '';

    return (
      <div className="Modal-body">
        {search}

        {flat.length ? (
          <div className="CuecardPicker-list" role="listbox" aria-label={t('title')}>
            {this.section(t('global_heading'), shared, 0)}
            {this.section(t('personal_heading'), yours, shared.length)}
          </div>
        ) : (
          <p className="CuecardPicker-empty">{total ? t('no_results') : t('empty')}</p>
        )}

        {flat.length ? <p className="CuecardPicker-hint">{t('insert_hint')}</p> : null}

        {canSave ? (
          <div className="CuecardPicker-footer">
            <Button className="Button" icon="fas fa-plus" onclick={() => this.edit()}>
              {t('new_button')}
            </Button>
            {composerText && composerText.trim() ? (
              <Button className="Button" icon="far fa-floppy-disk" onclick={() => this.edit(null, composerText)}>
                {t('save_reply_button')}
              </Button>
            ) : null}
          </div>
        ) : null}
      </div>
    );
  }

  section(heading, macros, offset) {
    if (!macros.length) return null;

    return (
      <div className="CuecardPicker-section">
        <h4 className="CuecardPicker-heading">{heading}</h4>
        <ul>{macros.map((macro, i) => this.row(macro, offset + i))}</ul>
      </div>
    );
  }

  row(macro, i) {
    const active = i === this.index;
    const badges = [];

    if (macro.lock()) badges.push(<Icon name="fas fa-lock" />);
    if ((macro.tagIds() || []).length) badges.push(<Icon name="fas fa-tag" />);
    if (macro.bestAnswer()) badges.push(<Icon name="fas fa-check" />);

    return (
      <li
        key={macro.id()}
        className={classList('CuecardPicker-item', active && 'active')}
        role="option"
        aria-selected={active ? 'true' : 'false'}
        onmouseenter={() => (this.index = i)}
      >
        <button type="button" className="CuecardPicker-insert" onclick={() => this.insert(macro)}>
          <span className="CuecardPicker-title">
            {macro.title()}
            {badges.length ? <span className="CuecardPicker-badges">{badges}</span> : null}
          </span>
          <span className="CuecardPicker-preview">{preview(macro.content(), this.format)}</span>
        </button>
        {macro.canEdit() ? (
          <span className="CuecardPicker-controls">
            <Tooltip text={t('edit_button')}>
              <Button
                className="Button Button--icon Button--link"
                icon="fas fa-pencil-alt"
                aria-label={t('edit_button')}
                onclick={() => this.edit(macro)}
              />
            </Tooltip>
            <Tooltip text={t('delete_button')}>
              <Button
                className="Button Button--icon Button--link"
                icon="far fa-trash-alt"
                aria-label={t('delete_button')}
                onclick={() => this.remove(macro)}
              />
            </Tooltip>
          </span>
        ) : null}
      </li>
    );
  }

  onkeydown(e) {
    // From the query as it is now, not the last redraw: Enter can come before the next frame.
    const flat = this.loaded ? this.macros().flat : [];
    const count = flat.length;

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!count) return;
      this.index = (this.index + (e.key === 'ArrowDown' ? 1 : count - 1)) % count;
      // Draw the move now, then bring the chosen row into view.
      m.redraw.sync();
      this.$('.CuecardPicker-item.active')[0]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (count) this.insert(flat[Math.min(this.index, count - 1)]);
    }
  }

  insert(macro) {
    const editor = app.composer.editor;

    if (!editor) return;

    // A macro written before the forum moved to Scribe is still Markdown: Scribe
    // translates it as text, so its placeholders must not be HTML-escaped.
    const format = this.format === 'html' && !/^\s*</.test(macro.content()) ? 'plain' : this.format;
    const text = fill(macro.content(), placeholderValues(), format);

    if (app.composer.bodyMatches('flarum/forum/components/ReplyComposer')) {
      const actions = allowedActions(macro, app.composer.body.attrs.discussion);

      if (actions) addActions(app.composer.body.attrs, actions);
    }

    this.hide();
    // false: let a Markdown-aware editor (FoF Rich Text) read the Markdown rather than type it out.
    editor.insertAtCursor(text, false);
    editor.focus();
  }

  edit(macro = null, content = null) {
    // Stacked over the picker. Flarum hands focus back to whatever had it when
    // a modal opened, so the search box takes it first and gets it back after.
    this.onready();
    app.modal.show(MacroEditorModal, { macro, content }, true);
  }

  remove(macro) {
    if (!confirm(app.translator.trans('ernestdefoe-cuecard.forum.picker.delete_confirm', { title: macro.title() }, true))) return;

    macro.delete().then(() => m.redraw());
  }
}
