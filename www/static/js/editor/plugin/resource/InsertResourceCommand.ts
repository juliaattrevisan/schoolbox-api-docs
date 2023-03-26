/**
 * Handles the insertion of a resource into the editor
 */

import Command from "@ckeditor/ckeditor5-core/src/command";
import { getAttributes } from "./resourceUtils";
import type { Resource } from "./resourceUtils";
import type { EditorConfig } from "@js/editor/types/editorTypes";

/**
 * Display the resource modal to select a file from
 */
function displayResourceModal() {
    /**
     * Homepages have a hidden input field containing the homepage id
     * If that exists, use it; otherwise assume root node 0
     */
    const defaultFolderSelector = $("#categoryID").val() ? $("#categoryID").val() : 0;
    /**
     * Fire of the jQuery plugin to display the resource modal
     */
    $.fn.schoolboxResource({
        allowedFilesystems: "files",
        allowedFiles: "*/*",
        allowMulti: true,
        useFolder: false,
        defaultFolderSelector,
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
async function getResourceInfo(hash: string) {
    try {
        const response = await fetch(`/editor/nodeInfo.php?identifier=${hash}`, {
            headers: {
                "Content-Type": "application/json",
            },
        });
        const resource = (await response.json()) as Resource;
        const attributes = getAttributes(resource, editor.config.sbConfig as EditorConfig);
        const imageUtils = editor.plugins.get("ImageUtils");
        imageUtils.insertImage(attributes, editor.model.document.selection, "imageInline");
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
function onResourceReturned(event: MessageEvent) {
    let message;
    try {
        message = JSON.parse(event.data);
    } catch (error: SyntaxError) {
        message = event.data;
    }
    if (message.data !== undefined) {
        if (0 in message.data && "hash" in message.data[0]) {
            window.removeEventListener("message", onResourceReturned);
            getResourceInfo(message.data[0].hash as string);
        } else {
            // The file was rejected, probably cause it was the wrong mimetype.
            // Do nothing
        }
    } else {
        // Connecting to your Google Drive (event is fired when the drive is first accessed)
    }
}

let editor; // the editor instance

export default class InsertResourceCommand extends Command {
    /**
     * display the resource modal and listen for if a resource is returned
     */
    execute() {
        editor = this.editor;
        displayResourceModal();
        window.removeEventListener("message", onResourceReturned);
        window.addEventListener("message", onResourceReturned);
    }

    /**
     * is called on various events to determine if the command is enabled
     * we use the same method the image plugin uses to determine if the command is enabled
     */
    refresh() {
        this.isEnabled = this.editor.plugins.get("ImageUtils").isImageAllowed();
    }
}
