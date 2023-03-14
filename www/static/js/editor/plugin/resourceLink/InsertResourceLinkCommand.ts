/**
 * Handles the insertion of a resource into the editor
 */

import Command from "@ckeditor/ckeditor5-core/src/command";
import { getSrc } from "@js/editor/plugin/resource/resourceUtils";
import type { Resource } from "@js/editor/plugin/resource/resourceUtils";
import type { EditorConfig } from "@js/editor/types/editorTypes";

export default class InsertResourceLinkCommand extends Command {
    editor; // the editor instance

    constructor(editor) {
        super(editor);
        editor = this.editor;
        // add an isOn property that will be used to determine if the resource modal is open
        // refer to https://github.com/ckeditor/ckeditor5/blob/bfe5a4c8fcbfe4292c51462cf595923d23b554d3/packages/ckeditor5-core/_src/command.js
        this.set("isOn", false);
    }

    /**
     * display the resource modal and listen for if a resource is returned
     */
    execute() {
        console.warn(this);
        this.set("isOn", true);
        this.displayResourceModal();
        window.removeEventListener("message", this.onResourceReturned.bind(this));
        window.addEventListener("message", this.onResourceReturned.bind(this));
    }

    /**
     * is called on various events to determine if the command is enabled
     * we use the same method the image plugin uses to determine if the command is enabled
     */
    refresh() {
        this.isEnabled = this.editor.plugins.get("ImageUtils").isImageAllowed();
    }

    /**
     * Display the resource modal to select a file from
     */
    displayResourceModal() {
        /**
         * Fire of the jQuery plugin to display the resource modal
         */
        $.fn.schoolboxResource({
            allowedFilesystems: "link",
            allowedFiles: null,
            onAfterClose: () => {
                console.warn("closed eh");
            },
        });
        // this.modal.foundation("reveal", "open", { close_on_background_click: this.closeOnBackgroundClick });
        $(document).on("closed.fndtn.reveal", "[data-reveal]", (event) => {
            console.warn("closed yay");
            // $(document).off("closed.fndtn.reveal");
        });
    }

    /**
     * Get the resource information from the server and
     * then insert the image/video/audio into the editor using the ImageUtils plugin.
     * Note that all three are inserted as images, but the extra attributes added to
     * audio/video will be used to display outside editing mode.
     *
     * See www/static/js/editor/jquery.proxy-replacement.js for how this occurs
     *
     * @param { string } hash - the hash of the stored file
     */
    async getResourceInfo(hash: string) {
        try {
            const response = await fetch(`/editor/nodeInfo.php?identifier=${hash}`, {
                headers: {
                    "Content-Type": "application/json",
                },
            });
            const resource = (await response.json()) as Resource;
            const src = getSrc(resource, this.editor.config.sbConfig as EditorConfig);
            console.warn(src);
        } catch (error) {
            console.error(error);
        }
        // to close the resource modal
        $("#file-picker").foundation("reveal", "close");
    }

    /**
     * Handle the returned resource from the resource modal
     *
     * @param { MessageEvent } event - the event
     */
    onResourceReturned(event: MessageEvent) {
        this.set("isOn", false);
        let message;
        try {
            message = JSON.parse(event.data);
        } catch (error: SyntaxError) {
            message = event.data;
        }
        if (message.data !== undefined) {
            if (0 in message.data && "hash" in message.data[0]) {
                window.removeEventListener("message", this.onResourceReturned);
                this.getResourceInfo(message.data[0].hash as string);
            } else {
                // The file was rejected, probably cause it was the wrong mimetype.
                // Do nothing
            }
        } else {
            // Connecting to your Google Drive (event is fired when the drive is first accessed)
        }
    }
}
