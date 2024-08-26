/**
 * Returns the tokens settings for the editor
 */

import type { MentionConfig } from "@ckeditor/ckeditor5-mention";
import type { SbxEditorConfig } from "@js/editor/types/editorTypes";
import { replaceAllStringSections, StringSection } from "@js/util/stringUtils";

const DEFAULT_TOKENS = [
    "[firstname]",
    "[preferredname]",
    "[surname]",
    "[fullname]",
    "[house]",
    "[year]",
    "[they]",
    "[They]",
    "[them]",
    "[Them]",
    "[theirs]",
    "[Theirs]",
];

/**
 * If tokens are enabled, return the tokens settings
 * @param { SbxEditorConfig } config - the config to use when creating the editor
 * @returns { { mention: MentionConfig } | Record<PropertyKey, never> } - the tokens settings if enabled
 */
export function getTokensSettings(config: SbxEditorConfig): { mention: MentionConfig } | Record<PropertyKey, never> {
    if (config.hasTokens) {
        return {
            mention: {
                dropdownLimit: 15,
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
 * Wraps a token in a span element if it isn't already wrapped
 * @param { string } sourceElementContent - the sourceElement content as HTML
 * @param { string } token - the token to wrap
 * @returns { string } - the updated content with the token wrapped in a span element
 */
function wrapToken(sourceElementContent: string, token: string): string {
    let currentIndex = sourceElementContent.indexOf(token);

    // if there's no matches return the original content
    if (currentIndex === -1) {
        return sourceElementContent;
    }

    // find all the indexes of the token
    const indexes: number[] = [];
    while (currentIndex !== -1) {
        indexes.push(currentIndex);
        currentIndex = sourceElementContent.indexOf(token, currentIndex + 1);
    }

    const mentionAttribute = `data-mention="`;
    const openSpanTag = `<span class="mention" ${mentionAttribute}${token}">`;
    const closeSpanTag = `</span>`;
    const wholeSpanTag = `${openSpanTag}${token}${closeSpanTag}`;

    // filter out any indexes that are already within a span tag
    const indexesNotWrapped = indexes.filter((index) => {
        let startIndex = index - openSpanTag.length;
        if (startIndex > 0 && sourceElementContent.substring(startIndex, index) === openSpanTag) {
            return false;
        }
        startIndex = index - mentionAttribute.length;
        if (startIndex > 0 && sourceElementContent.substring(startIndex, index) === mentionAttribute) {
            return false;
        }
        return true;
    });

    // if all found are already with a span tag, return the original content
    if (indexesNotWrapped.length === 0) {
        return sourceElementContent;
    }

    // otherwise get the start and end indexes of the sections to replace
    const sectionsToReplace: StringSection[] = indexesNotWrapped.map((index) => {
        return { startIndex: index, endIndex: index + token.length - 1 };
    });

    // and replace each with the whole span tag
    const updatedContent: string = replaceAllStringSections(sectionsToReplace, sourceElementContent, wholeSpanTag);

    return updatedContent;
}

/**
 * CKEditor5 provides special formatting around tokens, previously with CKE4, tokens were not surrounded with any other element,
 * with CKE5, tokens are now surrounded by a span element with a data-mention attribute
 * this function was added to help migrate text from CKE4 to CKE5, it will surround any tokens that are not already surrounded by a data-mention attribute
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @param { SbxEditorConfig } config - the config to use when creating the editor
 * @returns { HTMLElement } - the html with the tokens surrounded by the span element
 */
export function wrapTokens(sourceElementHTML: HTMLElement, config: SbxEditorConfig): HTMLElement {
    const tokens = config.tokenList ? config.tokenList : DEFAULT_TOKENS;
    let updatedContent = sourceElementHTML.innerHTML;
    tokens.forEach((token) => {
        updatedContent = wrapToken(updatedContent, token);
    });
    sourceElementHTML.innerHTML = updatedContent;
    return sourceElementHTML;
}
