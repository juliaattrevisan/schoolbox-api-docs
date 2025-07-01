/**
 * The InlineEditor type is not currently handled by CKEditor5, so we need to create a custom editor handler
 * that extends the AbstractEditorHandler class and overrides the defaultOnEnter method to move the editor
 * For further details refer to:
 * https://github.com/ckeditor/ckeditor5/blob/eb368c23848a56ccfe42fd32f9f30c139a1570bb/packages/ckeditor5-fullscreen/src/fullscreencommand.ts
 *
 */

import { AbstractEditorHandler, type InlineEditor } from "ckeditor5";

export default class CustomEditorHandler extends AbstractEditorHandler {
    protected override readonly _editor: InlineEditor;

    // Assign the custom editor class.
    constructor(editor: InlineEditor) {
        super(editor);

        this._editor = editor;
    }

    /**
     * A function that moves the editor UI elements to the fullscreen mode.
     * @returns The wrapper element that contains the editor UI elements in fullscreen mode.
     */
    public override defaultOnEnter(): HTMLElement {
        // This is just a copy/paste of the decoupled editor found here:
        // https://github.com/ckeditor/ckeditor5/blob/eb368c23848a56ccfe42fd32f9f30c139a1570bb/packages/ckeditor5-fullscreen/src/handlers/decouplededitorhandler.ts

        this.moveToFullscreen(this._editor.ui.getEditableElement()!, "editable");
        this.moveToFullscreen(this._editor.ui.view.toolbar.element!, "toolbar");

        this._editor.ui.view.toolbar.switchBehavior(
            this._editor.config.get("fullscreen.toolbar.shouldNotGroupWhenFull") === true ? "static" : "dynamic",
        );

        if (this._editor.config.get("fullscreen.menuBar.isVisible")) {
            const menuBarElement = this._editor.ui.view.menuBarView?.element;
            if (menuBarElement) {
                this.moveToFullscreen(menuBarElement, "menu-bar");
            }
        }

        return this.getWrapper();
    }
}
