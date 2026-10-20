import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Icon from 'flarum/common/components/Icon';
import { PLACEHOLDERS, fold } from '../placeholders';
import MacroTextEditor from './MacroTextEditor';

const t = (key, params) => app.translator.trans('ernestdefoe-cuecard.forum.editor.' + key, params);
const enabled = (id) => !!flarum.extensions[id];

/** Primary tags by position, each followed by its children, then the rest by name. */
function sortTags(tags) {
  const byPosition = (a, b) => (a.position() ?? 0) - (b.position() ?? 0);
  const top = tags.filter((tag) => tag.position() !== null && !tag.parent()).sort(byPosition);
  const out = [];

  top.forEach((tag) => {
    out.push(tag);
    tags
      .filter((child) => child.parent() === tag)
      .sort(byPosition)
      .forEach((child) => out.push(child));
  });

  return [...out, ...tags.filter((tag) => !out.includes(tag)).sort((a, b) => a.name().localeCompare(b.name()))];
}

/** A native checkbox, as core's own forms use. */
function checkbox(checked, onchange, label, className = '') {
  return (
    <label className={'checkbox ' + className}>
      <input type="checkbox" checked={checked} onchange={(e) => onchange(e.target.checked)} />
      {label}
    </label>
  );
}

/**
 * Write or change a macro. The text is written in the forum's own editor, so
 * what is saved is what the composer will receive.
 */
export default class MacroEditorModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);

    const macro = this.attrs.macro;

    this.manage = !!app.forum.attribute('cuecardManage');
    this.personal = !!app.forum.attribute('cuecardSavePersonal');

    this.title_ = macro ? macro.title() : '';
    this.content_ = macro ? macro.content() : this.attrs.content || '';
    // Staff who may share macros usually mean to: shared is their default.
    this.isGlobal = macro ? macro.isGlobal() : this.manage;
    // A new shared macro starts out offered to Moderators (group 4), the usual audience.
    this.groupIds = new Set(macro ? (macro.groups() || []).filter(Boolean).map((group) => group.id()) : ['4']);
    this.lock = macro ? !!macro.lock() : false;
    this.tagIds = new Set(macro ? (macro.tagIds() || []).map(String) : []);
    this.replaceTags = macro ? !!macro.replaceTags() : false;
    this.bestAnswer = macro ? !!macro.bestAnswer() : false;
    this.tagFilter = '';

    // A stand-in for the composer: the editor stores its driver here.
    this.host = { editor: null };

    this.tagsLoaded = !enabled('flarum-tags');
    if (!this.tagsLoaded) {
      app.tagList
        .load(['parent'])
        .then(() => (this.tagsLoaded = true))
        .finally(() => m.redraw());
    }
  }

  className() {
    return 'CuecardEditor';
  }

  /**
   * novalidate: an editor's own toolbar can bring hidden required fields
   * into the form (FoF Rich Text's link dialog does), and the browser would
   * refuse to submit. The server validates the macro.
   */
  wrapper(children) {
    return (
      <form novalidate onsubmit={this.onsubmit.bind(this)}>
        {children}
      </form>
    );
  }

  title() {
    return this.attrs.macro ? t('edit_title') : t('new_title');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label for="cuecard-title">{t('title_label')}</label>
            <input
              id="cuecard-title"
              name="title"
              className="FormControl"
              maxlength="100"
              value={this.title_}
              oninput={(e) => (this.title_ = e.target.value)}
            />
          </div>

          <div className="Form-group">
            <label>{t('content_label')}</label>
            <div className="CuecardEditor-text">
              <MacroTextEditor
                composer={this.host}
                value={this.content_}
                placeholder=""
                onchange={(value) => {
                  // The editor's input events are not Mithril's: redraw only when Save's state can change.
                  const was = !!this.content_.trim();
                  this.content_ = value;
                  if (was !== !!value.trim()) m.redraw();
                }}
                onsubmit={() => this.save()}
              />
            </div>
            <details className="CuecardEditor-placeholders">
              <summary>{t('placeholders_label')}</summary>
              <ul>
                {PLACEHOLDERS.map((name) => (
                  <li>
                    <code>{'{' + name + '}'}</code> {app.translator.trans('ernestdefoe-cuecard.forum.placeholders.' + name)}
                  </li>
                ))}
              </ul>
            </details>
          </div>

          {this.scopeFields()}
          {this.actionFields()}

          <div className="Form-group">
            {/* Not a submit button: an editor can put forms of its own (FoF Rich Text's link dialog) between it and ours. */}
            <Button className="Button Button--primary CuecardEditor-save" loading={this.loading} disabled={!this.ready()} onclick={() => this.save()}>
              {t('save_button')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  scopeFields() {
    // Only someone who can do both gets to choose; a saved macro keeps its scope.
    const choose = !this.attrs.macro && this.manage && this.personal;

    return (
      <>
        {choose ? (
          <div className="Form-group">
            <label>{t('scope_label')}</label>
            <div className="CuecardEditor-checks">
              {[true, false].map((global) => (
                <label className="checkbox">
                  <input type="radio" name="cuecard-scope" checked={this.isGlobal === global} onchange={() => (this.isGlobal = global)} />
                  {t(global ? 'scope_global' : 'scope_personal')}
                </label>
              ))}
            </div>
          </div>
        ) : null}

        {this.isGlobal && this.manage ? (
          <div className="Form-group">
            <label>{t('groups_label')}</label>
            <div className="CuecardEditor-groups">
              {app.store
                .all('groups')
                .filter((group) => group.id() !== '2')
                .map((group) =>
                  checkbox(
                    this.groupIds.has(group.id()),
                    (checked) => this.toggle(this.groupIds, group.id(), checked),
                    <>
                      {group.icon() ? <Icon name={group.icon()} /> : null} {group.namePlural()}
                    </>
                  )
                )}
            </div>
            <p className="helpText">{t('groups_help')}</p>
          </div>
        ) : null}
      </>
    );
  }

  actionFields() {
    const lock = enabled('flarum-lock');
    const tags = enabled('flarum-tags');
    const best = enabled('fof-best-answer');

    if (!lock && !tags && !best) return null;

    return (
      <div className="Form-group CuecardEditor-actions">
        <label>{t('actions_label')}</label>
        <p className="helpText">{t('actions_help')}</p>

        <div className="CuecardEditor-checks">
          {lock ? checkbox(this.lock, (checked) => (this.lock = checked), t('lock_label')) : null}
          {best ? checkbox(this.bestAnswer, (checked) => (this.bestAnswer = checked), t('best_answer_label')) : null}
        </div>

        {tags ? this.tagFields() : null}
      </div>
    );
  }

  tagFields() {
    if (!this.tagsLoaded) return <LoadingIndicator display="inline" size="small" />;

    const words = fold(this.tagFilter);
    const all = sortTags(app.store.all('tags'));
    const shown = all.filter((tag) => !words || fold(tag.name()).includes(words) || this.tagIds.has(tag.id()));

    return (
      <div className="CuecardEditor-tags">
        <label>{t('tags_label')}</label>
        {all.length > 8 ? (
          <input
            className="FormControl CuecardEditor-tagFilter"
            type="search"
            placeholder={t('tags_search_placeholder')}
            aria-label={t('tags_search_placeholder')}
            value={this.tagFilter}
            oninput={(e) => (this.tagFilter = e.target.value)}
          />
        ) : null}
        <div className="CuecardEditor-tagList">
          {shown.map((tag) =>
            checkbox(
              this.tagIds.has(tag.id()),
              (checked) => this.toggle(this.tagIds, tag.id(), checked),
              <>
                <span className="CuecardEditor-tagColor" style={{ background: tag.color() || 'var(--control-bg)' }} /> {tag.name()}
              </>,
              tag.parent() ? 'CuecardEditor-tag--child' : ''
            )
          )}
        </div>
        {this.tagIds.size ? checkbox(this.replaceTags, (checked) => (this.replaceTags = checked), t('replace_tags_label')) : null}
      </div>
    );
  }

  /** Title, text, and for a shared macro at least one group. */
  ready() {
    return this.title_.trim() && this.content_.trim() && !(this.isGlobal && this.manage && !this.groupIds.size);
  }

  toggle(set, id, checked) {
    if (checked) set.add(id);
    else set.delete(id);
  }

  onsubmit(e) {
    e.preventDefault();
    this.save();
  }

  save() {
    if (this.loading || !this.ready()) return;

    const macro = this.attrs.macro || app.store.createRecord('cuecard-macros');
    const data = {
      title: this.title_,
      content: this.content_,
      lock: this.lock,
      bestAnswer: this.bestAnswer,
      tagIds: [...this.tagIds].map(Number),
      replaceTags: this.tagIds.size ? this.replaceTags : false,
    };

    if (!this.attrs.macro) data.isGlobal = this.isGlobal;
    if (this.isGlobal && this.manage) {
      data.relationships = { groups: [...this.groupIds].map((id) => app.store.getById('groups', id)).filter(Boolean) };
    }

    this.loading = true;

    macro.save(data).then(
      () => this.hide(),
      (error) => {
        this.loading = false;
        this.onerror(error);
      }
    );
  }
}
