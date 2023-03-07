/**
 * Handles the toggling on/off to make the selected media full width
 */

import Command from "@ckeditor/ckeditor5-core/src/command";

export default class ToggleFullWidthCommand extends Command {
    constructor(editor) {
        super(editor);
        // add an isOn property that will be used to determine if the button is on or off
        // refer to https://github.com/ckeditor/ckeditor5/blob/bfe5a4c8fcbfe4292c51462cf595923d23b554d3/packages/ckeditor5-core/_src/command.js
        this.set("isOn", false);
    }

    /**
     * toggle the selected image to be full width
     */
    execute() {
        const editor = this.editor;
        const model = editor.model;
        const imageUtils = editor.plugins.get("ImageUtils");
        const imageElement = imageUtils.getClosestSelectedImageElement(model.document.selection);

        if (imageElement.getAttribute("data-schoolboxresource-fullwidth") === "true") {
            // if the image is full width, remove the full width styling
            editor.execute("resizeImage", { width: null });
            editor.execute("imageStyle", { value: false });
            model.change((writer) => {
                writer.setAttribute("data-schoolboxresource-fullwidth", "false", imageElement);
            });
        } else {
            // otherwise, make the image full width
            editor.execute("resizeImage", { width: "100%" });
            editor.execute("imageStyle", { value: "fullWidth" });
            model.change((writer) => {
                writer.setAttribute("data-schoolboxresource-fullwidth", "true", imageElement);
            });
        }
    }

    /**
     * is called whenever there's a new selection in the editor, when there's a new
     * selection we need to determine if the toggle button should be on or off
     * and whether the button should be enabled or disabled
     */
    refresh() {
        this.isEnabled = this.editor.plugins.get("ImageUtils").isImageAllowed();
        const editor = this.editor;
        const model = editor.model;
        const imageUtils = editor.plugins.get("ImageUtils");
        const imageResize = editor.plugins.get("ImageResize");
        const imageElement = imageUtils.getClosestSelectedImageElement(model.document.selection);
        if (imageElement && imageElement.getAttribute("data-schoolboxresource-fullwidth") === "true") {
            this.set("isOn", true);

            // console.warn(editor.ui.view.toolbar.items.find((item) => item.label && item.label.startsWith("Bold")));
            // console.warn(editor.plugins.get("ImageToolbar"));
            imageResize.remove();
            // console.warn(editor.ui.componentFactory.get("resizeImage"));
            // editor.ui.componentFactory.get("resizeImage").isEnabled = false;
        } else {
            this.set("isOn", false);
        }
    }
}
