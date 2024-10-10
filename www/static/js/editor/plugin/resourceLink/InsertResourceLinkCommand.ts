/**
 * Handles the insertion of a resource into the editor
 */

import { Command, type Editor } from "@ckeditor/ckeditor5-core";
import { getSrc } from "@js/editor/plugin/resource/resourceUtils";
import type { Resource } from "@js/editor/plugin/resource/resourceUtils";
import type { SbxEditorConfig } from "@js/editor/types/editorTypes";
import type LinkUI from "@js/editor/plugin/link/LinkUI";

export default class InsertResourceLinkCommand extends Command {
    /**
     * Whether the button is on or off
     */
    public declare isOn: boolean;

    // @ts-expect-error -- as .bind() returns a new function object, we need to store it in a variable
    messageBinding;

    /**
     * To open the Resource modal and insert the selected resource into the editor
     * @param editor - the editor instance
     */
    constructor(editor: Editor) {
        super(editor);
        // add an isOn property that will be used to determine if the resource modal is open
        // refer to https://github.com/ckeditor/ckeditor5/blob/bfe5a4c8fcbfe4292c51462cf595923d23b554d3/packages/ckeditor5-core/_src/command.js
        this.set("isOn", false);
    }

    /**
     * display the resource modal and listen for if a resource is returned
     */
    execute(): void {
        this.displayModal();
    }

    /**
     * is called on various events to determine if the command is enabled
     * we use the same method as the image command to determine
     */
    refresh(): void {
        const linkCommand = this.editor.commands.get("link") as Command;
        this.isEnabled = linkCommand.isEnabled;
    }

    /**
     * Display the resource modal to select a file from
     */
    displayModal(): void {
        /**
         * Ensure that selected files are not received by other event listeners made by other schoolboxResource functions on the page
         * For example, this prevents file drops from receiving the file selected in the editor.
         */
        $(window).unbind("message");
        /**
         * Fire of the jQuery plugin to display the resource modal
         */
        // @ts-expect-error -- This is a custom jQuery plugin, so it won't be found in the types
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        $.fn.schoolboxResource({
            allowedFilesystems: "link",
            allowedFiles: null,
            fileBrowserCategoryId: (this.editor.config.get("sbConfig") as SbxEditorConfig).fileBrowserCategoryId,
            onBeforeOpen: () => {
                this.set("isOn", true);
                this.messageBinding = this.onResourceReturned.bind(this);
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument -- Intentional use to ensure binding of .this from jquery.modal.js
                window.addEventListener("message", this.messageBinding);
            },
            onModalClosed: () => {
                // Once the modal is completely closed, the LinkUI can be closed again
                this.set("isOn", false);
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument -- Intentional use to ensure binding of .this from jquery.modal.js
                window.removeEventListener("message", this.messageBinding);
            },
        });
    }

    /**
     * to close the resource modal and allow the LinkUI to be also closed again
     */
    closeModal(): void {
        // @ts-expect-error -- jquery plugin
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        $("#file-picker").foundation("reveal", "close");
        this.set("isOn", false);
    }

    /**
     * Update the link in the LinkUI to the selected resource
     * @param value - the value to update the link to
     */
    updateLink(value: string): void {
        const linkUI = this.editor.plugins.get("LinkUI") as LinkUI;
        const { formView } = linkUI;
        if (formView === null) return;
        formView.urlInputView.fieldView.focus();
        formView.urlInputView.fieldView.value = value;
    }

    /**
     * Get the resource information from the server and
     * then insert the link into the editor
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
            const src = getSrc(resource, this.editor.config.get("sbConfig") as SbxEditorConfig) as string;
            this.updateLink(src);
        } catch (error) {
            console.error(error);
        }
        this.closeModal();
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
            // if the data is an array, it is a file
            if (0 in message.data && "hash" in message.data[0]) {
                let status = "success";
                if ("status" in message.data[0]) {
                    status = message.data[0].status;
                }
                if (status === "success") {
                    void this.getResourceInfo(message.data[0].hash as string);
                } else {
                    // Unknown status: assume error and do nothing, as the modal already shows the error
                }
                // Microsoft Teams meetings url format
            } else if ("meetingUrl" in message.data) {
                this.updateLink(message.data.meetingUrl);
                this.closeModal();
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
