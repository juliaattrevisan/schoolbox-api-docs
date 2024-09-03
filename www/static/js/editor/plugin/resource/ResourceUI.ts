/**
 * The Resource button, setting up the button to be displayed in the toolbar
 * and to trigger he insertResource command on selection
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import { ButtonView } from "@ckeditor/ckeditor5-ui";
import { insertElementAfter } from "@js/util/domUtils";
import type { ClassicEditor } from "@ckeditor/ckeditor5-editor-classic";
import type InsertResourceCommand from "./InsertResourceCommand";

export default class ResourceUI extends Plugin {
    uploadContainer!: HTMLDivElement; // the container for the upload progress bars

    /**
     * Get the plugin name, so can access with editor.plugins.get("ResourceUI")
     * @returns the plugin name
     */
    static get pluginName(): string {
        return "ResourceUI";
    }

    /**
     * Register the button in the editor's UI component factory.
     */
    init(): void {
        this.createButton();
        this.addUploadContainer();
    }

    /**
     * Create the button
     */
    createButton(): void {
        this.editor.ui.componentFactory.add("resource", () => {
            // Create the button and bind it to the command
            const icon =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M11.627 16.5zm5.873-.196zm0-7.001V8h-13v8.5h4.341c.191.54.457 1.044.785 1.5H2a1.5 1.5 0 0 1-1.5-1.5v-13A1.5 1.5 0 0 1 2 2h4.5a1.5 1.5 0 0 1 1.06.44L9.122 4H16a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 19 8v2.531a6.027 6.027 0 0 0-1.5-1.228zM16 6.5v-1H8.5l-2-2H2v13h1V8a1.5 1.5 0 0 1 1.5-1.5H16z"></path><path d="M14.5 19.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10zM15 14v-2h-1v2h-2v1h2v2h1v-2h2v-1h-2z"></path></svg>';
            const button: ButtonView = new ButtonView();
            button.set({
                label: "Embed Resources",
                withText: false,
                tooltip: true,
                icon,
            });
            button.on("execute", () => this.editor.execute("insertResource"));
            // Disable the button when the command is disabled
            const command = this.editor.commands.get("insertResource") as InsertResourceCommand;
            button.bind("isOn", "isEnabled").to(command, "value", "isEnabled");
            return button;
        });
    }

    /**
     * To display the upload progress of each resource being added to the editor.
     * This sits below the bottom of the editor and shows the progress of each resource.
     */
    addUploadContainer(): void {
        const uploadContainerAttribute = "data-resource-upload-container";
        const uploadContainer = (
            (this.editor as ClassicEditor).sourceElement?.parentElement as HTMLElement
        ).querySelector(`[${uploadContainerAttribute}]`);
        if (uploadContainer === null) {
            this.uploadContainer = document.createElement("div");
            this.uploadContainer.setAttribute(uploadContainerAttribute, "");
            this.uploadContainer.classList.add("dropzone-wrap", "sb-ck-resource-upload-container");
            this.checkUploadContainer();
            insertElementAfter((this.editor as ClassicEditor).sourceElement as HTMLElement, this.uploadContainer);
        } else {
            this.uploadContainer = uploadContainer as HTMLDivElement;
        }
    }

    /**
     * To hide/show the upload container based on if there are any uploads in progress
     */
    checkUploadContainer(): void {
        this.uploadContainer.style.display = this.uploadContainer.children.length ? "block" : "none";
    }

    /**
     * To display the progress of a resource being uploaded, based off the already
     * existing dropzone usages
     * @param file - the file being uploaded
     * @returns the element to display the progress
     */
    getUploadElement(file: File): HTMLDivElement {
        const container = document.createElement("div");
        container.classList.add("card", "small-12", "sb-ck-resource-upload-container__upload");
        container.innerHTML = `
            <p>${file.name}</p>
            <p class="meta" data-status>Uploading...</p>
            <div class="progress">
                <span class="meter" style="width: 0%" data-progress-meter></span>
            </div>
            <nav>
                <a href="#" data-remove-upload="" title="Remove" class="icon-close"></a>
            </nav>
        `;
        this.uploadContainer.appendChild(container);
        return container;
    }

    /**
     * To show the error state of a failed resource upload
     * @param element - the element to display the error
     * @param error - the error message to display
     */
    static showUploadErrorElement(element: HTMLDivElement, error: string): void {
        element.setAttribute("data-has-error", "");
        const status = element.querySelector("[data-status]") as HTMLParagraphElement;
        status.innerText = error;
    }
}
