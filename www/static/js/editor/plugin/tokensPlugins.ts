/**
 * Returns the tokens plugins for the editor
 */

import { Mention } from "@ckeditor/ckeditor5-mention";

import { SbxEditorConfig } from "@js/editor/types/editorTypes";

/**
 * If tokens are enabled, return the tokens settings
 * @param config - the config to use when creating the editor
 * @returns - the tokens settings
 */
export function getTokensPlugins(config: SbxEditorConfig): (typeof Mention)[] | [] {
    if (config.hasTokens) {
        return [Mention];
    }
    return [];
}
