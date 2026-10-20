import TextEditor from 'flarum/common/components/TextEditor';

/**
 * The forum's own text editor, so a macro is written in exactly the format the
 * composer will receive it in: Markdown, BBCode, FoF Rich Text, or Scribe's
 * HTML. Its submit button, preview and rich-text switch belong to the composer
 * and are left out; the modal has its own Save.
 */
export default class MacroTextEditor extends TextEditor {
  controlItems() {
    const items = super.controlItems();

    ['submit', 'preview', 'rich-text'].forEach((key) => items.has(key) && items.remove(key));

    return items;
  }
}
