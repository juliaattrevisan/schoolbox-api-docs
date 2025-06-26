/**
 * The InlineEditor type is not currently handled by CKEditor5, so we need to create a custom editor handler
 * that extends the AbstractEditorHandler class and overrides the defaultOnEnter method to move the editor
 * For further details refer to:
 * https://github.com/ckeditor/ckeditor5/blob/eb368c23848a56ccfe42fd32f9f30c139a1570bb/packages/ckeditor5-fullscreen/src/fullscreencommand.ts
 *
 */

import { AbstractEditorHandler, MenuBarView, type InlineEditor } from "ckeditor5";

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
        const editorUI = this._editor.ui;
        const editorUIView = editorUI.view;

        this.moveToFullscreen(editorUI.getEditableElement()!, "editable");
        this.moveToFullscreen(editorUIView.toolbar.element!, "toolbar");

        editorUIView.toolbar.switchBehavior(
            this._editor.config.get("fullscreen.toolbar.shouldNotGroupWhenFull") === true ? "static" : "dynamic",
        );

        // In classic editor, the `dir` attribute is set on the top-level container and it affects the styling
        // in both menu bar and toolbar (adding the side padding to the elements).
        // Since we don't move the whole container but only parts, we need to reapply the attribute value manually.
        // Decupled editor doesn't have this issue because there is no top-level container,
        // so `dir` attribute is set on each component separately.
        this.getWrapper().setAttribute("dir", editorUIView.element!.getAttribute("dir")!);

        // The `ck-rounded-corners` class is added to the wrapper element to ensure that the corners in menu bar, toolbar etc are rounded
        // when the editor is in fullscreen mode.
        // Decupled editor doesn't have this issue because there is no top-level container,
        // so `ck-rounded-corners` class is set on each component separately.
        this.getWrapper().classList.add("ck-rounded-corners");

        if (this._editor.config.get("fullscreen.menuBar.isVisible")) {
            if (!editorUIView.menuBarView) {
                editorUIView.menuBarView = new MenuBarView(this._editor.locale);
                editorUIView.menuBarView.render();
                editorUI.initMenuBar(editorUIView.menuBarView);
            }

            this.moveToFullscreen(editorUIView.menuBarView.element!, "menu-bar");
        }

        return this.getWrapper();
    }
}
