/**
 * To automatically embed resources into the editor when they are
 * dragged and dropped into the editor
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import { slideUp } from "@js/util/animateUtils";
import { isHtmlIncluded } from "@ckeditor/ckeditor5-image/src/imageupload/imageuploadediting";

export default class ResourceEditing extends Plugin {
    /**
     * Runs on startup, defines the schema and converters
     */
    init() {
        const editor = this.editor;
        const fileRepository = editor.plugins.get("FileRepository");
        const resourceUI = editor.plugins.get("ResourceUI");
        editor.editing.view.document.on("clipboardInput", (event, data) => {
            /**
             * Skip if non empty HTML data is included.
             * https://github.com/ckeditor/ckeditor5-upload/issues/68
             * Also seems to prevent uploading the same file twice when dragging an existing image
             * in the editor to a new spot
             */
            if (isHtmlIncluded(data.dataTransfer)) {
                return;
            }
            const files = Array.from(data.dataTransfer._files);
            files.forEach((file) => {
                this.displayUploadProgress(fileRepository, resourceUI, file as File);
            });
        });
    }

    /**
     * Add an element to display the upload progress of the resource
     *
     * @param { FileRepository } fileRepository - the file repository
     * @param { ResourceUI } resourceUI - the resource UI plugin
     * @param { File } file - the file to upload
     */
    displayUploadProgress(fileRepository, resourceUI, file: File) {
        const element = resourceUI.getProgressElement(file);
        const meta = element.querySelector("[data-progress-meter]");
        const loader = fileRepository.createLoader(file);
        loader.on("change:uploadedPercent", (evt, name, value) => this.onUploadProgress(value, element, meta));
        loader.upload();
        resourceUI.checkUploadContainer();
    }

    /**
     * To update the progress of the upload
     *
     * @param { number } value - the percentage of the upload
     * @param { HTMLDivElement } element - the element to update
     * @param { HTMLDivElement } meta - the element to update
     */
    onUploadProgress(value, element, meta) {
        meta.style.width = `${value}%`;
        if (value === 100) {
            this.removeUploadProgress(element);
        }
    }

    /**
     * To remove the upload progress element, removing the upload container if
     * there are no more uploads in progress
     *
     * @param { HTMLDivElement } element - the element to remove
     */
    async removeUploadProgress(element) {
        await slideUp(element);
        element.remove();
        this.editor.plugins.get("ResourceUI").checkUploadContainer();
    }
}
