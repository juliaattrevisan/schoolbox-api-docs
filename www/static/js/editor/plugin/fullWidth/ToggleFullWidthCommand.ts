/**
 * Handles the toggling on/off to make the selected media full width
 */

import { Command, type Editor } from "@ckeditor/ckeditor5-core";
import type { Element } from "@ckeditor/ckeditor5-engine";
import type { ImageResizeHandles, ImageResizeButtons } from "@ckeditor/ckeditor5-image";

export default class ToggleFullWidthCommand extends Command {
    /**
     * Whether the button is on or off
     */
    public declare isOn: boolean;

    constructor(editor: Editor) {
        super(editor);
        // add an isOn property that will be used to determine if the button is on or off
        // refer to https://github.com/ckeditor/ckeditor5/blob/bfe5a4c8fcbfe4292c51462cf595923d23b554d3/packages/ckeditor5-core/_src/command.js
        this.set("isOn", false);
    }

    /**
     * toggle the selected image to be full width
     */
    execute(): void {
        const { editor } = this;
        const { model } = editor;
        const imageUtils = editor.plugins.get("ImageUtils");
        const imageElement = imageUtils.getClosestSelectedImageElement(model.document.selection) as Element;
        if (imageElement.getAttribute("data-schoolboxresource-fullwidth") === "true") {
            // if the image is full width, remove the full width styling
            editor.execute("resizeImage", { width: null });
            editor.execute("imageStyle", { value: "" });
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
    refresh(): void {
        this.isEnabled = this.editor.plugins.get("ImageUtils").isImageAllowed();
        const { editor } = this;
        const { model } = editor;
        const imageUtils = editor.plugins.get("ImageUtils");
        const imageResizeHandles = editor.plugins.get("ImageResizeHandles") as ImageResizeHandles;
        const imageResizeButtons = editor.plugins.get("ImageResizeButtons") as ImageResizeButtons;
        const imageElement = imageUtils.getClosestSelectedImageElement(model.document.selection);
        if (imageElement && imageElement.getAttribute("data-schoolboxresource-fullwidth") === "true") {
            this.set("isOn", true);
            // when full width is active disable the resize handles and buttons
            imageResizeHandles.forceDisabled("fullWidth");
            imageResizeButtons.forceDisabled("fullWidth");
        } else {
            this.set("isOn", false);
            imageResizeHandles.clearForceDisabled("fullWidth");
            imageResizeButtons.clearForceDisabled("fullWidth");
        }
    }
}
