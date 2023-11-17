/**
 * Returns the toolbar to display in the editor, either as single buttons, dividers or
 * groups of buttons with a drop down to access provided
 */

import { EditorConfig, EditorToolbar } from "@js/editor/types/editorTypes";

/**
 * Display the AI toolbar options based on the existence of an sbProp
 * created within templates/core/include/chrome.twig
 * @returns {string[]} - the toolbar display
 */
function getToolbarAI() {
    if (window.sbProps.hasEditorAI) {
        return ["|", "aiCommands", "aiAssistant"];
    }
    return [];
}

/**
 * Display the toolbar options based on config
 * @param { EditorConfig } config - the config to use when creating the editor
 * @returns { Array } - the toolbar display
 */
export function getToolbar(config: EditorConfig) {
    // what displays in the default toolbar in order, are either single buttons or groups via objects
    let items: Array<string | object> = [];
    if (config.toolbar === EditorToolbar.SIMPLE) {
        items = [
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "removeFormat",
            "|",
            "link",
            "|",
            "emoji",
            "|",
            "numberedList",
            "bulletedList",
        ];
    } else if (config.toolbar === EditorToolbar.COMMENT) {
        items = [
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "blockQuote",
            "removeFormat",
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
        items = [
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
            "alignment",
            "|",
            "style",
            "|",
            "heading",
            ...getToolbarAI(),
            "|",
            "link",
            "resource",
            "mediaEmbed",
            "emoji",
            "insertTable",
            {
                label: "Insert",
                icon: "plus",
                items: [],
            },
            "|",
            "numberedList",
            "bulletedList",
            "|",
            {
                label: "Other",
                icon: "threeVerticalDots",
                items: ["specialCharacters", "eqneditor5Button", "horizontalLine", "findAndReplace", "sourceEditing"],
            },
        ];
    }
    /**
     * remove any individual plugins for specific use cases
     * refer to removeItems at: https://ckeditor.com/docs/ckeditor5/latest/features/toolbar/toolbar.html
     */
    let removeItems: Array<string> = [];
    if (config.removeFromToolbar) {
        removeItems = config.removeFromToolbar;
    }
    /**
     * add any individual plugins for specific use cases for example
     * the Blank plugin for Quiz cloze questions
     */
    if (config.addToToolbar) {
        items = [...items, ...config.addToToolbar];
    }
    const toolbar = {
        items,
        removeItems,
    };
    return toolbar;
}
