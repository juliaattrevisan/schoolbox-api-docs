/**
 * Returns the tokens plugins for the editor
 */

import { EditorConfig } from "@js/editor/types/editorTypes";

/**
 * Display the toolbar options based on config
 *
 * @param { EditorConfig } config - the config to use when creating the editor
 * @returns { Array } - the toolbar display
 */
export function getToolbar(config: EditorConfig) {
    return [
        // what displays in the default toolbar in order, are either single buttons or groups via objects
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
            items: [
                "insertImage",
                "mediaEmbed",
                "resource",
                "insertTable",
                "specialCharacters",
                "emoji",
                "horizontalLine",
            ],
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
        "undo",
        "redo",
        "|",
        "findAndReplace",
        "|",
        "sourceEditing",
    ];
}
