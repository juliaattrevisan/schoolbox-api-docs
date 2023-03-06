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
        this.editor.execute("resizeImage", { width: "100%" });
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
