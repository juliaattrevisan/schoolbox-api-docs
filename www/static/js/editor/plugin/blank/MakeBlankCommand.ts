/**
 * Handles the toggling on/off to make the selected media full width
 */

import Command from "@ckeditor/ckeditor5-core/src/command";

export default class MakeBlankCommand extends Command {
    constructor(editor) {
        super(editor);
        // add an isOn property that will be used to determine if the button is on or off
        // refer to https://github.com/ckeditor/ckeditor5/blob/bfe5a4c8fcbfe4292c51462cf595923d23b554d3/packages/ckeditor5-core/_src/command.js
        this.set("isOn", false);
    }

    /**
     * wrap the selected text in the blank element
     */
    execute() {
        const editor = this.editor;
        editor.model.change((writer) => {
            const blank = writer.createElement("blank");
            for (const range of editor.model.document.selection.getRanges()) {
                writer.wrap(range, blank);
            }
        });
    }

    /**
     * is called whenever there's a new selection in the editor, when there's a new
     * selection we need to determine if the toggle button should be on or off
     * and whether the button should be enabled or disabled
     */
    refresh() {
        this.isEnabled = this.editor.plugins.get("ImageUtils").isImageAllowed();
        // const editor = this.editor;
        // const model = editor.model;
        // const imageUtils = editor.plugins.get("ImageUtils");
        // const imageResizeHandles = editor.plugins.get("ImageResizeHandles");
        // const imageResizeButtons = editor.plugins.get("ImageResizeButtons");
        // const imageElement = imageUtils.getClosestSelectedImageElement(model.document.selection);
        // if (imageElement && imageElement.getAttribute("data-schoolboxresource-fullwidth") === "true") {
        //     this.set("isOn", true);
        //     // when full width is active disable the resize handles and buttons
        //     imageResizeHandles.forceDisabled();
        //     imageResizeButtons.forceDisabled();
        // } else {
        //     this.set("isOn", false);
        //     imageResizeHandles.clearForceDisabled();
        //     imageResizeButtons.clearForceDisabled();
        // }
    }
}
