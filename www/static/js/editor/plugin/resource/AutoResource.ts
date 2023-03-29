/**
 * To automatically embed resources into the editor when they are
 * dragged and dropped into the editor
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import { slideUp } from "@js/util/animateUtils";
import { isHtmlIncluded } from "@ckeditor/ckeditor5-image/src/imageupload/imageuploadediting";
import type { Resource } from "@js/editor/plugin/resource/resourceUtils";
import { addResource } from "@js/editor/plugin/resource/resourceUtils";

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
                this.loadResource(fileRepository, resourceUI, file as File);
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
    async loadResource(fileRepository, resourceUI, file: File) {
        const element = resourceUI.getUploadElement(file, this.onCloseClicked);
        resourceUI.checkUploadContainer();
        const meta = element.querySelector("[data-progress-meter]");
        const loader = fileRepository.createLoader(file);
        try {
            loader.on("change:uploadedPercent", (event, name, value) => (meta.style.width = `${value}%`));
            const resource: Resource = await loader.upload();
            addResource(this.editor, resource);
            this.removeUploadProgress(element);
        } catch (error) {
            resourceUI.showUploadErrorElement(
                element,
                "Sorry, the file could not be upload as an unknown error has occurred."
            );
            const remove = element.querySelector("[data-remove-upload]");
            remove.addEventListener("click", () => this.removeUploadProgress(element));
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
