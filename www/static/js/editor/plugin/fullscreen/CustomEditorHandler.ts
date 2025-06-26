import { AbstractEditorHandler, type Editor } from "ckeditor5";

export default class CustomEditorHandler extends AbstractEditorHandler {
    // It's not mandatory to override `#_editor` property, but that will help TypeScript to properly handle the class.
    // Skip if you are not using TS.
    protected override readonly _editor: Editor;

    // Assign the custom editor class.
    constructor(editor: Editor) {
        super(editor);

        this._editor = editor;
    }

    public override defaultOnEnter() {
        // Implement your fullscreen logic here.
        // For DOM manipulation, use `this.moveToFullscreen()` helper to ensure the elements are properly cleaned up
        // after leaving fullscreen mode.
        console.warn(
            "CustomEditorHandler: defaultOnEnter is not implemented. Please implement your fullscreen logic here.",
        );
    }
}
