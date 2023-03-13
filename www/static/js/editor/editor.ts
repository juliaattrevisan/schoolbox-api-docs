/**
 * To create a default CKEditor instances
 */

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import type { Editor } from "@ckeditor/ckeditor5-core";
import { getConfig } from "@js/editor/config/editorConfig";
import { EditorConfig, EditorToolbar } from "@js/editor/types/editorTypes";
import {
    insertWordCount,
    removeValidation,
    addIframelyEmbed,
    addToGlobalScope,
    attachInspector,
} from "@js/editor/util/editorUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";
import "@js/editor/styles/editor.css"; // to overwrite some of the styles

/**
 * Add in any additional setup required and then dispatch that the editor is ready
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @param { Editor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 */
async function onCreated(textarea: HTMLTextAreaElement, editor: Editor, config: EditorConfig) {
    insertWordCount(textarea, editor);
    removeValidation();
    await addIframelyEmbed();
    addToGlobalScope(editor, textarea);
    editor.config.sbConfig = config;
    attachInspector(editor);
    textarea.dispatchEvent(
        new CustomEvent(EditorEvent.EDITOR__CREATED, {
            detail: {
                editor,
                textarea,
            },
        })
    );
}

/**
 * create the ckeditor instance
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @param { EditorConfig } config - the config to use when creating the editor
 */
function create(textarea: HTMLTextAreaElement, config: EditorConfig) {
    // if no toolbar is specified, use the full toolbar
    if (config.toolbar === undefined) {
        config.toolbar = EditorToolbar.FULL;
    }
    ClassicEditor.create(textarea, getConfig(config))
        .then((editor) => {
            onCreated(textarea, editor, config);
        })
        .catch((error) => {
            console.error(error.stack);
        });
}

/**
 * Listen for init events to create any instances
 *
 * @param { CustomEvent } event - the custom event
 */
function onCreate(event: CustomEvent) {
    const config = event.detail as EditorConfig;
    const { targetElements } = config;
    targetElements.forEach((targetElement) => {
        // assume that each targetElement could contain multiple instances
        document.querySelectorAll(targetElement).forEach((textarea) => {
            if (config.tokenList) {
                textarea.innerHTML = surroundUnsurroundedTokens(textarea.innerHTML, config.tokenList);
            }

            create(textarea as HTMLTextAreaElement, config);
        });
    });
}

/**
 * Surround tokens with span element
 *
 * @param { string } str - the string to search
 * @param { string[] } tokens - the tokens to search for
 * @returns { string } - the string with the tokens surrounded by the span element
 */
function surroundUnsurroundedTokens(str: string, tokens: string[]): string {
    // Regular expression for matching existing mentions
    const mentionRegex = /<span class="mention" data-mention="(\w+)"\/?>/g;

    let formattedStr = str;
    tokens.forEach(token => {
        // Regular expression for matching the token, exclude tokens that are already surrounded by a data-mention
        const tokenRegex = new RegExp(`(?<!data-mention=")\\${token}`, 'g');
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

document.addEventListener(EditorEvent.EDITOR__CREATE, onCreate);
