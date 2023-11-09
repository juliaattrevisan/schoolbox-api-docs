/**
 * Returns the tokens plugins for the editor
 */

import { Mention } from "@ckeditor/ckeditor5-mention";

import { EditorConfig } from "@js/editor/types/editorTypes";

/**
 * If tokens are enabled, return the tokens settings
 * @param { EditorConfig } config - the config to use when creating the editor
 * @returns { object } - the tokens settings
 */
export function getTokensPlugins(config: EditorConfig) {
    if (config.hasTokens) {
        return [Mention];
    }
    return [];
}
