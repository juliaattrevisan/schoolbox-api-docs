/**
 * Returns the tokens settings for the editor
 */

import { EditorConfig } from "@js/editor/types/editorTypes";

const DEFAULT_TOKENS = ["[firstname]", "[preferredname]", "[surname]", "[fullname]", "[house]", "[year]"];
/**
 * If tokens are enabled, return the tokens settings
 *
 * @param { EditorConfig } config - the config to use when creating the editor
 * @returns { object } - the tokens settings
 */
export function getTokensSettings(config: EditorConfig) {
    if (config.hasTokens) {
        return {
            mention: {
                feeds: [
                    {
                        marker: "[",
                        feed: config.tokenList ?? DEFAULT_TOKENS,
                        minimumCharacters: 0,
                    },
                ],
            },
        };
    }
    return {};
}
