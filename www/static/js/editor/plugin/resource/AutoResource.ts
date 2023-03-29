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
    totalUploadCount = 0; // the total number of files to upload

    uploadedCount = 0; // the number of files that have been uploaded so far

    /**
     * Runs on startup, start listening for clipboardInput, which is fired when
     * a file is dragged and dropped into the editor
     * When files are added, upload them and add them to the editor
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
            this.totalUploadCount = files.length;
            this.uploadedCount = 0;
            this.editor.enableReadOnlyMode("auto-resource");
            files.forEach((file) => {
                this.loadResource(fileRepository, resourceUI, file as File);
            });
        });
    }

    /**
     * Add an element to display the upload progress of the resource.
     * Once the resource is uploaded the upload element display is closed.
     * If there's an error the display will change to an error state.
     *
     * @param { FileRepository } fileRepository - the file repository
     * @param { ResourceUI } resourceUI - the resource UI plugin
     * @param { File } file - the file to upload
     */
    async loadResource(fileRepository, resourceUI, file: File) {
        const uploadElement = resourceUI.getUploadElement(file, this.onCloseClicked);
        resourceUI.checkUploadContainer();
        const meta = uploadElement.querySelector("[data-progress-meter]");
        const loader = fileRepository.createLoader(file);
        try {
            loader.on("change:uploadedPercent", (event, name, value) => (meta.style.width = `${value}%`));
            // www/static/js/editor/plugin/uploadAdapter/UploadAdapter.ts is the adapter that uploads files to the server
            const resource: Resource = await loader.upload();
            addResource(this.editor, resource);
            this.removeUploadProgress(uploadElement);
        } catch (error) {
            resourceUI.showUploadErrorElement(
                uploadElement,
                "Sorry, the file could not be upload as an unknown error has occurred."
            );
            const remove = uploadElement.querySelector("[data-remove-upload]");
            remove.addEventListener("click", () => this.removeUploadProgress(uploadElement));
        } finally {
            this.uploadedCount++;
            if (this.uploadedCount === this.totalUploadCount) {
                this.editor.disableReadOnlyMode("auto-resource");
            }
        }
    }

    /**
     * To remove the upload progress element, removing the upload container if
     * there are no more uploads in progress
     *
     * @param { HTMLDivElement } uploadElement - the element to remove
     */
    async removeUploadProgress(uploadElement) {
        await slideUp(uploadElement);
        uploadElement.remove();
        this.editor.plugins.get("ResourceUI").checkUploadContainer();
    }
}
