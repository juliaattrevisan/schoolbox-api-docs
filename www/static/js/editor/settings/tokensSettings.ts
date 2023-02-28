/**
 * Returns the tokens settings for the editor
 */

import { EditorDefaultConfig } from "@js/editor/config/configDefault";

/**
 * If tokens are enabled, return the tokens settings
 *
 * @param { EditorDefaultConfig } config - the config to use when creating the editor
 * @returns { object } - the tokens settings
 */
export function getTokensSettings(config: EditorDefaultConfig) {
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
