/**
 * To automatically embed non image resources into the editor when they are
 * dragged and dropped into the editor.
 * Note that images are handled more comprehensively by the native ImageUpload plugin
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import { slideUp } from "@js/util/animateUtils";
import { isHtmlIncluded } from "@ckeditor/ckeditor5-image/src/imageupload/imageuploadediting";
import type { ImageUploadCompleteEvent } from "@ckeditor/ckeditor5-image/src/imageupload/imageuploadediting";
import type { Resource } from "@js/editor/plugin/resource/resourceUtils";
import { addResource } from "@js/editor/plugin/resource/resourceUtils";
import ResourceUI from "@js/editor/plugin/resource/ResourceUI";
import type { SbxEditorConfig} from "@js/editor/types/editorTypes";
import { EditorToolbar } from "@js/editor/types/editorTypes";
import type { FileLoader, FileRepository } from "@ckeditor/ckeditor5-upload/";
import type { ViewDocumentClipboardInputEvent } from "@ckeditor/ckeditor5-clipboard/src/clipboardobserver";
import { createImageTypeRegExp } from "@ckeditor/ckeditor5-image/src/imageupload/utils";

export default class ResourceEditing extends Plugin {
    totalUploadCount = 0; // the total number of files to upload

    uploadedCount = 0; // the number of files that have been uploaded so far

    /**
     * Runs on startup, start listening for clipboardInput, which is fired when
     * a file is dragged and dropped into the editor
     * When files are added, upload them and add them to the editor
     */
    init(): void {
        const config = this.editor.config.get("sbConfig") as SbxEditorConfig;
        // prevent adding resources if the toolbar is set to simple
        if (config.toolbar === EditorToolbar.SIMPLE) {
            return;
        }
        const { editor } = this;
        const fileRepository = editor.plugins.get("FileRepository");
        const resourceUI = editor.plugins.get("ResourceUI") as ResourceUI;
        editor.editing.view.document.on<ViewDocumentClipboardInputEvent>("clipboardInput", (event, data) => {
            /**
             * Skip if non empty HTML data is included.
             * https://github.com/ckeditor/ckeditor5-upload/issues/68
             * Also seems to prevent uploading the same file twice when dragging an existing image
             * in the editor to a new spot
             */
            if (isHtmlIncluded(data.dataTransfer)) {
                return;
            }
            // Get the files from the clipboard dataTransfer object
            let files = Array.from(data.dataTransfer.files);
            const imageTypes = createImageTypeRegExp(["jpeg", "png", "gif", "bmp", "webp", "tiff"]);
            files = files.filter((file) => !imageTypes.test(file.type)); // ensure images aren't uploaded
            // Ensure there are still files to upload after images have been filtered
            if (files.length === 0) {
                return;
            }
            editor.model.change((writer) => {
                // Set selection to paste target, to ensure files are most accurately placed
                if (data.targetRanges) {
                    writer.setSelection(
                        data.targetRanges.map((viewRange) => editor.editing.mapper.toModelRange(viewRange)),
                    );
                }
                this.totalUploadCount = files.length;
                this.uploadedCount = 0;
                this.editor.enableReadOnlyMode("auto-resource");
                files.forEach((file) => {
                    void this.loadResource(fileRepository, resourceUI, file);
                });
            });
        });

        // When an image is uploaded, add the alt and linkHref attributes (so the image has a link to the original file)
        const imageUploadEditing = this.editor.plugins.get("ImageUploadEditing");
        imageUploadEditing.on<ImageUploadCompleteEvent>("uploadComplete", (evt, { data, imageElement }) => {
            this.editor.model.change((writer) => {
                writer.setAttribute("alt", data.name, imageElement);
                writer.setAttribute("linkHref", data.default, imageElement);
            });
        });
    }

    /**
     * Add an element to display the upload progress of the resource.
     * Once the resource is uploaded the upload element display is closed.
     * If there's an error the display will change to an error state.
     * @param fileRepository - the file repository
     * @param resourceUI - the resource UI plugin
     * @param file - the file to upload
     */
    async loadResource(fileRepository: FileRepository, resourceUI: ResourceUI, file: File): Promise<void> {
        const uploadElement = resourceUI.getUploadElement(file);
        resourceUI.checkUploadContainer();
        const progressMeter = uploadElement.querySelector("[data-progress-meter]") as HTMLDivElement;
        const loader = fileRepository.createLoader(file) as FileLoader;
        try {
            loader.on("change:uploadedPercent", (event, name, value: number) => {
                progressMeter.style.width = `${value}%`;
            });
            // www/static/js/editor/plugin/uploadAdapter/UploadAdapter.ts is the adapter that uploads files to the server
            const resource = (await loader.upload()) as Resource;
            addResource(this.editor, resource);
            await this.removeUploadProgress(uploadElement);
        } catch (error) {
            ResourceUI.showUploadErrorElement(
                uploadElement,
                "Sorry, the file could not be uploaded as an unknown error has occurred.",
            );
            const remove = uploadElement.querySelector("[data-remove-upload]") as HTMLDivElement;
            remove.addEventListener("click", () => {
                void this.removeUploadProgress(uploadElement);
            });
        } finally {
            this.uploadedCount += 1;
            if (this.uploadedCount === this.totalUploadCount) {
                this.editor.disableReadOnlyMode("auto-resource");
            }
            fileRepository.destroyLoader(loader);
        }
    }

    /**
     * To remove the upload progress element, removing the upload container if
     * there are no more uploads in progress
     * @param uploadElement - the element to remove
     */
    async removeUploadProgress(uploadElement: HTMLDivElement): Promise<void> {
        await slideUp(uploadElement);
        uploadElement.remove();
        (this.editor.plugins.get("ResourceUI") as ResourceUI).checkUploadContainer();
    }
}
