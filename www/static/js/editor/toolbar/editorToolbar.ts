/**
 * Returns the toolbar to display in the editor, either as single buttons, dividers or
 * groups of buttons with a drop down to access provided
 */

import type { ToolbarConfig, ToolbarConfigItem } from "ckeditor5";
import { EditorToolbar } from "@js/editor/types/editorTypes";
import type { SbxEditorConfig } from "@js/editor/types/editorTypes";

/**
 * Display the AI toolbar options based on the existence of an sbProp
 * created within templates/core/include/chrome.twig
 * @param config - the config to use when creating the editor
 * @returns - the toolbar display
 */
function getToolbarAI(config: SbxEditorConfig): string[] {
    if (config.markdown) {
        // AI features don't work correctly in markdown mode
        return [];
    }
    if (window.sbProps.aiFeatures.chat?.enabled) {
        return ["|", "aiCommands", "aiAssistant"];
    }
    return [];
}

/**
 * Display the toolbar options based on config
 * @param config - the config to use when creating the editor
 * @returns - the toolbar display
 */
export function getToolbar(config: SbxEditorConfig): ToolbarConfig {
    // what displays in the default toolbar in order, are either single buttons or groups via objects
    let items: ToolbarConfigItem[] = [];

    if (config.toolbar === EditorToolbar.SIMPLE) {
        items = [
            // if there are templates defined in the config, add the insertTemplate button
            ...(config.templates && config.templates.length > 0 ? ["insertTemplate", "|"] : []),
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "removeFormat",
            "|",
            "alignment",
            "|",
            ...getToolbarAI(config),
            "|",
            "link",
            "emoji",
            "|",
            "numberedList",
            "bulletedList",
        ];
    } else if (config.toolbar === EditorToolbar.COMMENT || config.toolbar === EditorToolbar.COMMENT_AI) {
        items = [
            ...(config.templates && config.templates.length > 0 ? ["insertTemplate", "|"] : []),
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "blockQuote",
            "removeFormat",
            "|",
            "alignment",
            "|",
            // If using the COMMENT_AI toolbar, add the AI commands and assistant in the right spot
            ...(config.toolbar === EditorToolbar.COMMENT_AI ? getToolbarAI(config) : []),
            "|",
            "link",
            "|",
            "resource",
            ...(config.markdown ? [] : ["mediaEmbed"]), // mediaEmbed doesn't work in markdown mode
            "emoji",
            "|",
            "numberedList",
            "bulletedList",
        ];
    } else if (config.markdown) {
        // default toolbar, but markdown mode
        // a lot of features from the normal default toolbar don't work particularly well with markdown
        items = [
            "fullscreen",
            "|",
            // if there are templates defined in the config, add the insertTemplate button
            ...(config.templates && config.templates.length > 0 ? ["insertTemplate", "|"] : []),
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "subscript",
            "superscript",
            "blockQuote",
            "removeFormat",
            "|",
            "heading",
            "|",
            "link",
            "|",
            "resource",
            "emoji",
            "|",
            "numberedList",
            "bulletedList",
            "|",
            {
                label: "Other",
                icon: "threeVerticalDots",
                items: [
                    "specialCharacters",
                    "eqneditor5Button",
                    "horizontalLine",
                    "findAndReplace",
                    "sourceEditingEnhanced",
                ],
            },
        ];
    } else {
        items = [
            "fullscreen",
            "|",
            // if there are templates defined in the config, add the insertTemplate button
            ...(config.templates && config.templates.length > 0 ? ["insertTemplate", "|"] : []),
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
            ...getToolbarAI(config),
            "|",
            "link",
            "bookmark",
            "|",
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
                items: [
                    "specialCharacters",
                    "eqneditor5Button",
                    "horizontalLine",
                    "findAndReplace",
                    "sourceEditingEnhanced",
                ],
            },
        ];
    }

    /**
     * add any individual plugins for specific use cases for example
     * the Blank plugin for Quiz cloze questions
     */
    if (config.addToToolbar) {
        config.addToToolbar.forEach((item) => {
            if (typeof item === "string") {
                // String items are appended to the end
                items.push(item);
            } else {
                // Object items with name and after properties are inserted after the specified item
                const afterIndex = items.findIndex((toolbarItem) => {
                    // Handle both string items and objects with label property
                    if (typeof toolbarItem === "string") {
                        return toolbarItem === item.after;
                    }
                    if (typeof toolbarItem === "object" && toolbarItem !== null && "label" in toolbarItem) {
                        return toolbarItem.label === item.after;
                    }
                    return false;
                });
                if (afterIndex !== -1) {
                    // Insert after the found item
                    items.splice(afterIndex + 1, 0, item.name);
                } else {
                    // If the after item is not found, append to the end
                    items.push(item.name);
                }
            }
        });
    }

    return {
        items,
        /**
         * remove any individual plugins for specific use cases
         * refer to removeItems at: https://ckeditor.com/docs/ckeditor5/latest/features/toolbar/toolbar.html
         */
        removeItems: config.removeFromToolbar || [],
        /**
         * the toolbar will stop grouping items and let them wrap to the next line
         * when there is not enough space to display them in a single row.
         */
        shouldNotGroupWhenFull: true,
    };
}
