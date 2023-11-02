/**
 * Handles the insertion of a resource into the editor
 */

import { Command } from "@ckeditor/ckeditor5-core";
import { addResource } from "./resourceUtils";
import type { Resource } from "./resourceUtils";

export default class InsertResourceCommand extends Command {
    messageBinding; // as .bind() returns a new function object, we need to store it in a variable

    /**
     * display the resource modal and listen for if a resource is returned
     */
    execute() {
        this.diplayModal();
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
    diplayModal() {
        /**
         * Homepages have a hidden input field containing the homepage id
         * If that exists, use it; otherwise assume root node 0
         */
        const defaultFolderSelector = $("#categoryID").val() ? $("#categoryID").val() : 0;
        /**
         * Ensure that selected files are not recieved by other event listeners made by other schoolboxResource functions on the page
         * For example, this prevents file drops from recieving the file selected in the editor.
         */
        $(window).unbind("message");
        /**
         * Fire off the jQuery plugin to display the resource modal
         */
        $.fn.schoolboxResource({
            allowedFilesystems: "files",
            allowedFiles: "*/*",
            allowMulti: true,
            useFolder: false,
            defaultFolderSelector,
            fileBrowserCategoryId: this.editor.config.get("sbConfig").fileBrowserCategoryId,
            onBeforeOpen: () => {
                this.messageBinding = this.onResourceReturned.bind(this);
                window.addEventListener("message", this.messageBinding);
            },
            onModalClosed: () => {
                window.removeEventListener("message", this.messageBinding);
            },
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
            const response = await fetch(`/editor/nodeInfo.php?identifier=${encodeURIComponent(hash)}`, {
                headers: {
                    "Content-Type": "application/json",
                },
            });
            const resource = (await response.json()) as Resource;
            addResource(this.editor, resource);
        } catch (error) {
            console.warn(error);
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
        let message;
        try {
            message = JSON.parse(event.data);
        } catch (error: SyntaxError) {
            message = event.data;
        }
        if (message.data !== undefined) {
            if (0 in message.data) {
                // for each resource returned, get the resource info and insert it into the editor
                const resources = message.data.filter((resource) => resource.hash !== undefined);
                resources.forEach((resource) => {
                    this.getResourceInfo(resource.hash as string);
                });
            } else {
                // The file was rejected, probably cause it was the wrong mimetype.
                // Do nothing
            }
        } else {
            // Connecting to your Google Drive (event is fired when the drive is first accessed)
        }
    }
}
