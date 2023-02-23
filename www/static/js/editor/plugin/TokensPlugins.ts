/**
 * Returns the tokens plugins for the editor
 */

import Mention from "@ckeditor/ckeditor5-mention/src/mention";
import Tokens from "./tokens/Tokens";

import { EditorDefaultConfig } from "@js/editor/config/configDefault";

/**
 * If tokens are enabled, return the tokens settings
 *
 * @param { EditorDefaultConfig } config - the config to use when creating the editor
 * @returns { object } - the tokens settings
 */
export function getTokensPlugins(config: EditorDefaultConfig) {
    if (config.hasTokens) {
        return [Mention, Tokens];
    }
    return [];
}
