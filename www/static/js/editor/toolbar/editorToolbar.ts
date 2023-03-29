/**
 * Returns the toolbar to display in the editor, either as single buttons, dividers or
 * groups of buttons with a drop down to access provided
 */

import { EditorConfig, EditorToolbar } from "@js/editor/types/editorTypes";

/**
 * Display the toolbar options based on config
 *
 * @param { EditorConfig } config - the config to use when creating the editor
 * @returns { Array } - the toolbar display
 */
export function getToolbar(config: EditorConfig) {
    // what displays in the default toolbar in order, are either single buttons or groups via objects
    let toolbar: Array<string | object> = [];
    if (config.toolbar === EditorToolbar.SIMPLE) {
        toolbar = [
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "blockQuote",
            "|",
            "link",
            "|",
            "emoji",
            "|",
            "numberedList",
            "bulletedList",
        ];
    } else if (config.toolbar === EditorToolbar.COMMENT) {
        toolbar = [
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "blockQuote",
            "|",
            "link",
            "|",
            "resource",
            "mediaEmbed",
            "emoji",
            "|",
            "numberedList",
            "bulletedList",
        ];
    } else {
        toolbar = [
            "bold",
            "italic",
            {
                label: "Styles",
                icon: "text",
                items: [
                    "underline",
                    "strikethrough",
                    "subscript",
                    "superscript",
                    "blockQuote",
                    "fontColor",
                    "fontBackgroundColor",
                    "fontSize",
                    "fontFamily",
                ],
            },
            "removeFormat",
            "|",
            "style",
            "|",
            "heading",
            "|",
            "link",
            "|",
            {
                label: "Insert",
                icon: "plus",
                items: ["resource", "mediaEmbed", "insertTable", "specialCharacters", "emoji", "horizontalLine"],
            },
            "|",
            {
                label: "Lists",
                icon: "threeVerticalDots",
                items: ["numberedList", "bulletedList", "todoList"],
            },
            "|",
            "alignment",
            "|",
            "findAndReplace",
            "|",
            "sourceEditing",
        ];
    }
    /**
     * remove any individual plugins for specific use cases
     * for each pluginName we loop through the toolbar, if the item is a string
     * we check if it matches the pluginName, if it does we replace it with null
     * if it is an object we filter out the items that match the pluginName
     * then we filter out any null values
     */
    if (config.removeFromToolbar) {
        config.removeFromToolbar.forEach((pluginName) => {
            toolbar = toolbar
                .map((item) => {
                    if (typeof item === "string") {
                        return item !== pluginName ? item : null;
                    }
                    const newItems = item.items.filter((subItem) => subItem !== pluginName);
                    return { ...item, items: newItems };
                })
                .filter((item) => item !== null);
        });
    }
    /**
     * add any individual plugins for specific use cases for example
     * the Blank plugin for Quiz cloze questions
     */
    if (config.addToToolbar) {
        toolbar = [...toolbar, ...config.addToToolbar];
    }
    return toolbar;
}
