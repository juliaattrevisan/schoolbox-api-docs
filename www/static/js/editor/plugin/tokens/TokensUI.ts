/**
 * The Token UI, which adds the base button as well as the dropdown options that are pulled from the config
 *
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import { addListToDropdown, createDropdown } from "@ckeditor/ckeditor5-ui";
import { Collection } from "@ckeditor/ckeditor5-utils";
import Model from "@ckeditor/ckeditor5-ui/src/model";

/**
 * Populate the list in the dropdown with items.
 *
 * @param { Array } tokenNames - the list of token names to add to the dropdown
 * @returns { Array } - the list of items to add to the dropdown
 */
function getDropdownItemsDefinitions(tokenNames) {
    const itemDefinitions = new Collection();
    for (const name of tokenNames) {
        const definition = {
            type: "button",
            model: new Model({
                commandParam: name,
                label: name,
                withText: true,
            }),
        };
        // Add the item definition to the collection.
        itemDefinitions.add(definition);
    }
    return itemDefinitions;
}

export default class TokensUI extends Plugin {
    init() {
        const editor = this.editor;
        const t = editor.t;
        const tokenNames = editor.config.get("mention.feeds")[0].feed;
        editor.ui.componentFactory.add("tokens", (locale) => {
            const tokenIcon =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M11.627 16.5zm5.873-.196zm0-7.001V8h-13v8.5h4.341c.191.54.457 1.044.785 1.5H2a1.5 1.5 0 0 1-1.5-1.5v-13A1.5 1.5 0 0 1 2 2h4.5a1.5 1.5 0 0 1 1.06.44L9.122 4H16a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 19 8v2.531a6.027 6.027 0 0 0-1.5-1.228zM16 6.5v-1H8.5l-2-2H2v13h1V8a1.5 1.5 0 0 1 1.5-1.5H16z"></path><path d="M14.5 19.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10zM15 14v-2h-1v2h-2v1h2v2h1v-2h2v-1h-2z"></path></svg>';
            const dropdownView = createDropdown(locale);
            addListToDropdown(dropdownView, getDropdownItemsDefinitions(tokenNames));
            dropdownView.buttonView.set({
                // The t() function helps localize the editor. All strings enclosed in t() can be
                // translated and change when the language of the editor changes.
                label: t("[tokens]"),
                tooltip: true,
                withText: false,
                icon: tokenIcon,
            });
            // Execute the command when the dropdown item is clicked (executed).
            this.listenTo(dropdownView, "execute", (event) => {
                // use the default mention command to insert the token
                editor.execute("mention", { marker: "[", mention: event.source.commandParam });
                editor.editing.view.focus();
            });
            return dropdownView;
        });
    }
}
