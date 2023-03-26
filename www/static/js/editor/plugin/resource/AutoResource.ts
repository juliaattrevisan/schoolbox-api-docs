/**
 * To automatically embed resources into the editor when they are
 * dragged and dropped into the editor
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class ResourceEditing extends Plugin {
    /**
     * Runs on startup, defines the schema and converters
     */
    init() {
        const editor = this.editor;
        const fileRepository = editor.plugins.get("FileRepository");
        editor.editing.view.document.on("clipboardInput", (event, data) => {
            const files = Array.from(data.dataTransfer._files);
            files.forEach((file) => {
                const loader = fileRepository.createLoader(file);
                loader.on("change:status", (evt, name, value) => {
                    console.warn("change:status: " + value);
                });
                loader.on("change:uploadedPercent", (evt, name, value) => {
                    console.warn("change:uploadedPercent: " + value);
                });
                loader.upload();
            });
        });
    }
}
