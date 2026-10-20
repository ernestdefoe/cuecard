import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import TextEditor from 'flarum/common/components/TextEditor';
import TextEditorButton from 'flarum/common/components/TextEditorButton';
import Post from 'flarum/common/models/Post';
import { actionChips, pendingActions, afterReply } from './actions';

/** Whether this person has any reason to open the picker. */
const canOpen = () => ['cuecardUse', 'cuecardSavePersonal', 'cuecardManage'].some((name) => app.forum.attribute(name));

let picker;

app.initializers.add('ernestdefoe-cuecard', () => {
  /*
   * The button sits in the main composer's toolbar only — not in other
   * text editors (the macro editor itself among them). The picker, the editor
   * and the macros are loaded the first time it is pressed.
   */
  extend(TextEditor.prototype, 'toolbarItems', function (items) {
    if (this.attrs.composer !== app.composer || !canOpen()) return;

    items.add(
      'cuecard',
      <TextEditorButton
        icon="fas fa-layer-group"
        onclick={() => {
          picker ??= import('./components/MacroPicker').catch((error) => {
            picker = null;
            throw error;
          });
          picker.then(({ default: MacroPicker }) => app.modal.show(MacroPicker));
        }}
      >
        {app.translator.trans('ernestdefoe-cuecard.forum.composer.button')}
      </TextEditorButton>,
      -50
    );
  });

  // Composers are lazy chunks in Flarum 2: extended by module name.
  extend('flarum/forum/components/ReplyComposer', 'headerItems', function (items) {
    const chips = actionChips(this.composer.body.attrs);

    if (chips) items.add('cuecard', chips, -10);
  });

  extend('flarum/forum/components/ReplyComposer', 'data', function (data) {
    const actions = pendingActions(this.composer.body.attrs);

    if (actions) data.cuecardActions = actions;
  });

  // The only place the saved reply comes back to: follow up on its actions.
  extend(Post.prototype, 'save', function (promise, attributes) {
    if (attributes && attributes.cuecardActions && promise && promise.then)
      promise.then(
        (post) => afterReply(post, attributes.cuecardActions),
        () => {}
      );
  });
});
