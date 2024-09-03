/**
 * Handles the insertion of a resource into the editor
 */

import { Command } from "@ckeditor/ckeditor5-core";
import type { SbxEditorConfig } from "@js/editor/types/editorTypes";
import { addResource } from "./resourceUtils";
import type { Resource } from "./resourceUtils";

export default class InsertResourceCommand extends Command {
    // @ts-expect-error -- as .bind() returns a new function object, we need to store it in a variable
    messageBinding;

    value: boolean = false; // has no value (its just a button), so always false to show as de-selected

    /**
     * display the resource modal and listen for if a resource is returned
     */
    execute(): void {
        this.displayModal();
    }

    /**
     * is called on various events to determine if the command is enabled
     * we use the same method the image plugin uses to determine if the command is enabled
     */
    refresh(): void {
        this.isEnabled = this.editor.plugins.get("ImageUtils").isImageAllowed();
    }

    /**
     * Display the resource modal to select a file from
     */
    displayModal(): void {
        /**
         * Homepages have a hidden input field containing the homepage id
         * If that exists, use it; otherwise assume root node 0
         */
        const defaultFolderSelector = $("#categoryID").val() ? $("#categoryID").val() : 0;
        /**
         * Ensure that selected files are not received by other event listeners made by other schoolboxResource functions on the page
         * For example, this prevents file drops from receiving the file selected in the editor.
         */
        $(window).unbind("message");
        /**
         * Fire off the jQuery plugin to display the resource modal
         */
        // @ts-expect-error -- This is a custom jQuery plugin, so it won't be found in the types
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        $.fn.schoolboxResource({
            allowedFilesystems: "files",
            allowedFiles: "*/*",
            allowMulti: true,
            useFolder: false,
            defaultFolderSelector,
            fileBrowserCategoryId: (this.editor.config.get("sbConfig") as SbxEditorConfig).fileBrowserCategoryId,
            onBeforeOpen: () => {
                this.messageBinding = this.onResourceReturned.bind(this);
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument -- Intentional use to ensure binding of .this from jquery.modal.js
                window.addEventListener("message", this.messageBinding);
            },
            onModalClosed: () => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument -- Intentional use to ensure binding of .this from jquery.modal.js
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
     * @param hash - the hash of the stored file
     */
    async getResourceInfo(hash: string): Promise<void> {
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
        // @ts-expect-error -- jquery plugin
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        $("#file-picker").foundation("reveal", "close");
    }

    /**
     * Handle the returned resource from the resource modal
     * @param event - the event
     */
    onResourceReturned(event: MessageEvent): void {
        // Disable eslint checks for this function, since it is integrating with jquery.modal.js and untyped
        /* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
        let message;
        try {
            message = JSON.parse(event.data);
        } catch (error) {
            message = event.data;
        }
        if (message.data !== undefined) {
            if (0 in message.data) {
                // @ts-expect-error -- disable typechecking for this, as it's integrating with jquery.modal.js and untyped
                // for each resource returned, get the resource info and insert it into the editor
                const resources = message.data.filter((resource) => resource.hash !== undefined);
                // @ts-expect-error -- disable typechecking for this, as it's integrating with jquery.modal.js and untyped
                resources.forEach((resource) => {
                    void this.getResourceInfo(resource.hash as string);
                });
            } else {
                // The file was rejected, probably cause it was the wrong mimetype.
                // Do nothing
            }
        } else {
            // Connecting to your Google Drive (event is fired when the drive is first accessed)
        }
        /* eslint-enable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
    }
}
