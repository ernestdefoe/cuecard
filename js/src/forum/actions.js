import app from 'flarum/forum/app';
import Tooltip from 'flarum/common/components/Tooltip';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';

/*
 * The actions of the macros inserted into a reply wait on the composer body's
 * attrs: they survive redraws and minimising, and a new composer starts with
 * none. Each can be dropped before posting.
 */

const enabled = (id) => !!flarum.extensions[id];

/** Which of a macro's actions this poster may take in this discussion, right now. */
export function allowedActions(macro, discussion) {
  if (!discussion) return null;

  const actions = {};

  if (macro.lock() && enabled('flarum-lock') && discussion.attribute('canLock') && !discussion.attribute('isLocked')) actions.lock = true;
  if ((macro.tagIds() || []).length && enabled('flarum-tags') && discussion.attribute('canTag')) {
    actions.tags = macro.tagIds().slice();
    actions.replaceTags = !!macro.replaceTags();
  }
  if (macro.bestAnswer() && enabled('fof-best-answer') && discussion.attribute('canSelectBestAnswer')) actions.bestAnswer = true;

  return Object.keys(actions).length ? actions : null;
}

/** Adds a macro's actions to those already waiting: tags combine, a replace wins. */
export function addActions(attrs, actions) {
  const pending = attrs.cuecardActions || {};

  if (actions.lock) pending.lock = true;
  if (actions.bestAnswer) pending.bestAnswer = true;
  if (actions.tags) {
    pending.tags = [...new Set([...(pending.tags || []), ...actions.tags])];
    pending.replaceTags = !!(pending.replaceTags || actions.replaceTags);
  }

  attrs.cuecardActions = pending;
}

/** What to send with the reply, or nothing. */
export function pendingActions(attrs) {
  const pending = attrs.cuecardActions;

  if (!pending) return null;

  const out = {};
  if (pending.lock) out.lock = true;
  if (pending.bestAnswer) out.bestAnswer = true;
  if (pending.tags && pending.tags.length) {
    out.tags = pending.tags;
    out.replaceTags = !!pending.replaceTags;
  }

  return Object.keys(out).length ? out : null;
}

function drop(attrs, key) {
  delete attrs.cuecardActions[key];
  if (key === 'tags') delete attrs.cuecardActions.replaceTags;
  if (!pendingActions(attrs)) delete attrs.cuecardActions;
}

function chip(attrs, key, iconName, label) {
  return (
    <span className="CuecardChip">
      <Icon name={iconName} />
      <span className="CuecardChip-label">{label}</span>
      <Tooltip text={app.translator.trans('ernestdefoe-cuecard.forum.composer.remove_action')}>
        <Button
          className="Button Button--icon Button--link CuecardChip-remove"
          icon="fas fa-times"
          aria-label={app.translator.trans('ernestdefoe-cuecard.forum.composer.remove_action', {}, true)}
          onclick={() => drop(attrs, key)}
        />
      </Tooltip>
    </span>
  );
}

function tagNames(ids) {
  return ids
    .map((id) => app.store.getById('tags', String(id)))
    .filter(Boolean)
    .map((tag) => tag.name())
    .join(', ');
}

/** The chips under the composer's header, one per waiting action. */
export function actionChips(attrs) {
  const pending = pendingActions(attrs);

  if (!pending) return null;

  const t = (key, params) => app.translator.trans('ernestdefoe-cuecard.forum.composer.' + key, params);

  return (
    <div className="CuecardChips">
      <span className="CuecardChips-label">{t('actions_label')}</span>
      {pending.lock ? chip(attrs, 'lock', 'fas fa-lock', t('action_lock')) : null}
      {pending.tags
        ? chip(attrs, 'tags', 'fas fa-tag', t(pending.replaceTags ? 'action_tags_replace' : 'action_tags', { tags: tagNames(pending.tags) }))
        : null}
      {pending.bestAnswer ? chip(attrs, 'bestAnswer', 'fas fa-check', t('action_best_answer')) : null}
    </div>
  );
}

/**
 * After the reply is saved: say which actions were refused, and bring the
 * discussion's tags up to date when they were changed (the reply's response
 * carries the discussion, but not its tags).
 */
export function afterReply(post, sent) {
  const failed = (post && post.attribute('cuecardFailed')) || [];
  const discussion = post && post.discussion();

  if (sent.tags && discussion && !failed.includes('tags')) {
    app.store.find('discussions', discussion.id(), { include: 'tags' }).then(() => m.redraw());
  }

  if (!failed.length) return;

  const t = (key, params) => app.translator.trans('ernestdefoe-cuecard.forum.composer.' + key, params, true);
  const keys = { lock: 'failed_lock', tags: 'failed_tags', bestAnswer: 'failed_best_answer' };
  const list = failed.filter((action) => keys[action]).map((action) => t(keys[action]));

  app.alerts.show({ type: 'warning' }, t('failed', { actions: list.join(t('failed_separator')) }));
}
