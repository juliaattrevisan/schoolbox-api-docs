/**
 * The Schoolbox Resource button
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ButtonView from "@ckeditor/ckeditor5-ui/src/button/buttonview";
import { fetchHtmlAsText } from "@js/util/fileUtils";

/**
 * Returns the button to display in the toolbar
 *
 * @returns { ButtonView } - the button to display in the toolbar
 */
function getButton() {
    const starIcon =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M11.627 16.5zm5.873-.196zm0-7.001V8h-13v8.5h4.341c.191.54.457 1.044.785 1.5H2a1.5 1.5 0 0 1-1.5-1.5v-13A1.5 1.5 0 0 1 2 2h4.5a1.5 1.5 0 0 1 1.06.44L9.122 4H16a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 19 8v2.531a6.027 6.027 0 0 0-1.5-1.228zM16 6.5v-1H8.5l-2-2H2v13h1V8a1.5 1.5 0 0 1 1.5-1.5H16z"></path><path d="M14.5 19.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10zM15 14v-2h-1v2h-2v1h2v2h1v-2h2v-1h-2z"></path></svg>';
    const button = new ButtonView();
    button.label = "Embed Resource";
    button.tooltip = true;
    button.withText = false;
    button.icon = starIcon;
    return button;
}

/**
 * Initiate the resource modal
 *
 * @param { number } editorId - the id of the editor to create unique file picker ids
 */
async function initResourceModal(editorId: number) {
    let html = await fetchHtmlAsText("/resources/attachment?multi=0");
    html = html.replace(/attachment-browser-filesystems/g, `attachment-browser-filesystems-${editorId}`);
    html = html.replace(/attachment-browser-panel-container/g, `attachment-browser-panel-container-${editorId}`);
    const $modal = $(`<div data-reveal class="reveal-modal file-picker" id="file-picker-${editorId}" />`);
    $modal.html(html);
    $modal.on("click", ".cancel[role=button]", () => {
        $modal.foundation("reveal", "close");
    });
    $("body").append($modal); // add to the DOM
    $modal.foundation("reveal", "open");
}

/**
 * Display the resource modal
 *
 * @param { number } editorId - the id of the editor to create unique file picker ids
 */
function displayResourceModal(editorId: number) {
    const $modal = $(`#file-picker-${editorId}`);
    $modal.foundation("reveal", "open");
}

export default class SchoolboxResourceUI extends Plugin {
    isModalInit = false; // whether the modal has been initiated so we only set up once

    editorId = 0; // the id of the editor to create unique file picker ids

    /**
     * To add the button to the toolbar and define
     * its behaviour when selected - which is opening the modal
     *
     * @returns { ButtonView } - the button to display in the toolbar
     */
    initButton() {
        const button = getButton();
        this.listenTo(button, "execute", () => {
            if (this.isModalInit) {
                displayResourceModal(this.editorId);
            } else {
                initResourceModal(this.editorId);
                this.isModalInit = true;
            }
        });
        return button;
    }

    /**
     * Register the button in the editor's UI component factory.
     */
    init() {
        this.editorId = this.editor.id;
        this.editor.ui.componentFactory.add("schoolboxResource", () => {
            return this.initButton();
        });
    }
}
