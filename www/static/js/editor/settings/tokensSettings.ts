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

/**
 * CKEditor5 provides special formatting around tokens, previously with CKE4, tokens were not surrounded with any other element,
 * with CKE5, tokens are now surrounded by a span element with a data-mention attribute
 * this function was added to help migrate text from CKE4 to CKE5, it will surround any tokens that are not already surrounded by a data-mention attribute
 *
 * @param { string } str - the string to surround the tokens in
 * @param { EditorConfig } config - the config to use when creating the editor
 * @returns { string } - the string with the tokens surrounded by the span element
 */
export function surroundUnsurroundedTokens(str: string, config: EditorConfig): string {
    const tokens = config.tokenList ? config.tokenList : DEFAULT_TOKENS;

    // Regular expression for matching existing mentions
    const mentionRegex = /<span class="mention" data-mention="(\w+)"\/?>/g;

    let formattedStr = str;
    tokens.forEach((token) => {
        // Regular expression for matching the token, exclude tokens that are already surrounded by a data-mention
        const tokenRegex = new RegExp(`(?<!data-mention=")\\${token}`, "g");
        let match;
        let lastIndex = 0;
        let tempStr = "";

        // Search for and replace all instances of the token in the formatted string
        while (true) {
            match = tokenRegex.exec(formattedStr);
            if (match === null) {
                break;
            }

            // Append any unmodified text between the last match and the current match
            tempStr += formattedStr.slice(lastIndex, match.index);

            // Check if the token is already surrounded by a mention
            const mentionMatch = mentionRegex.exec(tempStr.slice(match.index - 50, match.index));
            if (!mentionMatch || mentionMatch[1] !== token.slice(1, -1)) {
                // If the token is not already surrounded by a mention, add the mention tags
                tempStr += `<span class="mention" data-mention="${token.slice(1, -1)}">`;
                tempStr += match[0];
                tempStr += `</span>`;
            } else {
                // If the token is already surrounded by a mention, use the existing tags
                tempStr += match[0];
            }

            // Update the lastIndex variable to continue searching after the current match
            lastIndex = tokenRegex.lastIndex;
        }

        // Append any remaining unmodified text after the last match and update the formatted string
        //  to the version with the current token surrounded by mention tags
        tempStr += formattedStr.slice(lastIndex);
        formattedStr = tempStr;
    });

    return formattedStr;
}
