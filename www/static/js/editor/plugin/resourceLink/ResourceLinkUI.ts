/**
 * Add a button to the link form view to open the resource modal.
 * Selecting a link within the modal will update the link form view's URL field.
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ButtonView from "@ckeditor/ckeditor5-ui/src/button/buttonview";
import { insertElementAfter } from "@js/util/domUtils";

export default class ResourceLinkUI extends Plugin {
    button: ButtonView; // the button to open the resource modal

    /**
     * Initialize the plugin
     */
    init() {
        this.createButton();
        this.addButton();
    }

    /**
     * Create the button to open the resource modal
     */
    createButton() {
        this.button = new ButtonView(this.locale);
        this.button.set({
            label: "Browse",
            withText: true,
            tooltip: true,
            class: "ck-button-resource-link",
        });
        this.button.on("execute", () => this.editor.execute("insertResourceLink"));
        // Disable the button when the command is disabled
        // const command = this.editor.commands.get("insertResourceLink");
        // this.button.bind("isOn", "isEnabled").to(command, "value", "isEnabled");
    }

    /**
     * Add the button to the link form view
     * The link form view is not available when the plugin is initialized,
     * so we need to wait for the contextual balloon to be displayed and
     * check if the link form view is visible. If it is, we can add the button
     * and remove the listener. Unfortunately I haven't found a more elegant way
     * to check if the link form view is visible besidex the existence of a class yet
     */
    addButton() {
        const contextualBalloon = this.editor.plugins.get("ContextualBalloon");
        contextualBalloon.on("change:visibleView", (eventInfo, name, value, oldValue) => {
            if (value.element.classList.contains("ck-link-form")) {
                this.linkFormView = value;
                this.button.render();
                this.linkFormView.registerChild(this.button);
                // insert the button next to the URL field
                insertElementAfter(this.linkFormView.urlInputView.fieldView.element, this.button.element);
                // no longer need to listen for changes in the visible view
                contextualBalloon.off("change:visibleView");
            }
        });
    }
}
