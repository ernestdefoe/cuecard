import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Checkbox from 'flarum/common/components/Checkbox';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import icon from 'flarum/common/helpers/icon';
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
    this.isGlobal = macro ? macro.isGlobal() : !this.personal;
    this.groupIds = new Set(
      macro && macro.groups()
        ? macro
            .groups()
            .filter(Boolean)
            .map((group) => group.id())
        : []
    );
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
                onchange={(value) => (this.content_ = value)}
                onsubmit={() => this.save()}
              />
            </div>
            <p className="helpText CuecardEditor-placeholders">
              {PLACEHOLDERS.map((name) => (
                <span className="CuecardEditor-placeholder" title={app.translator.trans('ernestdefoe-cuecard.forum.placeholders.' + name, {}, true)}>
                  <code>{'{' + name + '}'}</code> {app.translator.trans('ernestdefoe-cuecard.forum.placeholders.' + name)}
                </span>
              ))}
            </p>
          </div>

          {this.scopeFields()}
          {this.actionFields()}

          <div className="Form-group">
            <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!this.title_.trim() || !this.content_.trim()}>
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
            {[false, true].map((global) => (
              <label className="checkbox">
                <input type="radio" name="cuecard-scope" checked={this.isGlobal === global} onchange={() => (this.isGlobal = global)} />
                {t(global ? 'scope_global' : 'scope_personal')}
              </label>
            ))}
          </div>
        ) : null}

        {this.isGlobal && this.manage ? (
          <div className="Form-group">
            <label>{t('groups_label')}</label>
            <div className="CuecardEditor-groups">
              {app.store
                .all('groups')
                .filter((group) => group.id() !== '2')
                .map((group) => (
                  <Checkbox state={this.groupIds.has(group.id())} onchange={(checked) => this.toggle(this.groupIds, group.id(), checked)}>
                    {group.icon() ? icon(group.icon()) : null} {group.namePlural()}
                  </Checkbox>
                ))}
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

        {lock ? (
          <Checkbox state={this.lock} onchange={(checked) => (this.lock = checked)}>
            {t('lock_label')}
          </Checkbox>
        ) : null}

        {best ? (
          <Checkbox state={this.bestAnswer} onchange={(checked) => (this.bestAnswer = checked)}>
            {t('best_answer_label')}
          </Checkbox>
        ) : null}

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
          {shown.map((tag) => (
            <Checkbox
              className={tag.parent() ? 'CuecardEditor-tag--child' : ''}
              state={this.tagIds.has(tag.id())}
              onchange={(checked) => this.toggle(this.tagIds, tag.id(), checked)}
            >
              <span className="CuecardEditor-tagColor" style={{ background: tag.color() || 'var(--control-bg)' }} /> {tag.name()}
            </Checkbox>
          ))}
        </div>
        {this.tagIds.size ? (
          <Checkbox state={this.replaceTags} onchange={(checked) => (this.replaceTags = checked)}>
            {t('replace_tags_label')}
          </Checkbox>
        ) : null}
      </div>
    );
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
    if (this.loading || !this.title_.trim() || !this.content_.trim()) return;

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
