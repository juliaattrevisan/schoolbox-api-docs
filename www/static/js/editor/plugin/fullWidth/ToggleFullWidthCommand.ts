/**
 * Handles the toggling of ensuring an image is full width
 */

import Command from "@ckeditor/ckeditor5-core/src/command";

let editor; // the editor instance

export default class ToggleFullWidthCommand extends Command {
    /**
     * toggle the selected image to be full width
     */
    execute() {
        const editor = this.editor;
        const model = editor.model;
        const imageUtils = editor.plugins.get("ImageUtils");
        const imageElement = imageUtils.getClosestSelectedImageElement(model.document.selection);

        if (imageElement.getAttribute("data-schoolboxresource-fullwidth") === "true") {
            editor.execute("resizeImage", { width: null });
            editor.execute("imageStyle", { value: false });
            model.change((writer) => {
                writer.setAttribute("data-schoolboxresource-fullwidth", false, imageElement);
            });
        } else {
            editor.execute("resizeImage", { width: "100%" });
            editor.execute("imageStyle", { value: "fullWidth" });
            model.change((writer) => {
                writer.setAttribute("data-schoolboxresource-fullwidth", true, imageElement);
            });
        }

        // editor = this.editor;
    }

    /**
     * is called on various events to determine if the command is enabled
     * we use the same method the image plugin uses to determine if the command is enabled
     */
    refresh() {
        this.isEnabled = this.editor.plugins.get("ImageUtils").isImageAllowed();
    }
}
