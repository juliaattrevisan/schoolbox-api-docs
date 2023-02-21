/**
 * The Token UI, which adds the base button as well as the dropdown options that are pulled from the config
 *
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import { addListToDropdown, createDropdown } from "@ckeditor/ckeditor5-ui/src/dropdown/utils";
import Collection from "@ckeditor/ckeditor5-utils/src/collection";
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
            const dropdownView = createDropdown(locale);
            addListToDropdown(dropdownView, getDropdownItemsDefinitions(tokenNames));
            dropdownView.buttonView.set({
                // The t() function helps localize the editor. All strings enclosed in t() can be
                // translated and change when the language of the editor changes.
                label: t("[tokens]"),
                tooltip: true,
                withText: false,
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
