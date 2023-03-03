/**
 * Returns the tokens settings for the editor
 */

import { EditorConfig } from "@js/editor/types/editorTypes";

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
                        feed: ["[firstname]", "[preferredname]", "[surname]", "[fullname]", "[house]", "[year]"],
                        minimumCharacters: 0,
                    },
                ],
            },
        };
    }
    return {};
}
