/**
 * To automatically embed resources into the editor when they are
 * dragged and dropped into the editor
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import { slideUp } from "@js/util/animateUtils";

function displayUpload(fileRepository, resourceUI, file: File) {
    const element = resourceUI.getProgressElement(file);
    const meta = element.querySelector("[data-progress-meter]");
    const loader = fileRepository.createLoader(file);
    loader.on("change:uploadedPercent", (evt, name, value) => {
        meta.style.width = `${value}%`;
        if (value === 100) {
            slideUp(element);
        }
    });
    loader.upload();
}

export default class ResourceEditing extends Plugin {
    /**
     * Runs on startup, defines the schema and converters
     */
    init() {
        const editor = this.editor;
        const fileRepository = editor.plugins.get("FileRepository");
        const resourceUI = editor.plugins.get("ResourceUI");
        editor.editing.view.document.on("clipboardInput", (event, data) => {
            const files = Array.from(data.dataTransfer._files);
            files.forEach((file) => {
                displayUpload(fileRepository, resourceUI, file as File);
            });
        });
    }
}
