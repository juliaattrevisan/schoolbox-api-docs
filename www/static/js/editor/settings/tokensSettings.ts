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
 * CKEditor5 does not allow headings to exit within <li> elements, this wraps any heading/pre elements within lists
 * in a <span> or <tt> element to mimic the behaviour of CKEditor4
 *
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } - the html with the listed headings wrapped in a span element
 */
export function fixListedHeadings(sourceElementHTML: HTMLElement): string {
    let str = sourceElementHTML.innerHTML;
    str = str.replace(/<li>\s*<h2>(.*?)<\/h2>\s*<\/li>/gi, '<li><span style="font-size:1.125rem">$1</span></li>');
    str = str.replace(/<li>\s*<h3>(.*?)<\/h3>\s*<\/li>/gi, '<li><span style="font-size:1.0625rem;">$1</span></li>');
    str = str.replace(/<li>\s*<h4>(.*?)<\/h4>\s*<\/li>/gi, '<li><span style="font-size:1rem;">$1</span></li>');
    str = str.replace(/<li>\s*<pre>(.*?)<\/pre>\s*<\/li>/gi, '<li><tt class="">$1</tt></li>');
    sourceElementHTML.innerHTML = str;
    return sourceElementHTML;
}

/**
 * CKEditor5 provides special formatting around tokens, previously with CKE4, tokens were not surrounded with any other element,
 * with CKE5, tokens are now surrounded by a span element with a data-mention attribute
 * this function was added to help migrate text from CKE4 to CKE5, it will surround any tokens that are not already surrounded by a data-mention attribute
 *
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @param { EditorConfig } config - the config to use when creating the editor
 * @returns { HTMLElement } - the html with the tokens surrounded by the span element
 */
export function surroundUnsurroundedTokens(sourceElementHTML: HTMLElement, config: EditorConfig): HTMLElement {
    const tokens = config.tokenList ? config.tokenList : DEFAULT_TOKENS;

    // Regular expression for matching existing mentions
    const mentionRegex = /<span class="mention" data-mention="(\w+)"\/?>/g;

    let formattedStr = sourceElementHTML.innerHTML;
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
    sourceElementHTML.innerHTML = formattedStr;
    return sourceElementHTML;
}
