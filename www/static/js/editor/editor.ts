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
    const tokensRegex = new RegExp(tokens.map(token => `\\${token}`).join('|'), 'g');
    const newStr = str.replace(tokensRegex, (match: string) => {
      // Check if the keyword is already surrounded by the span element
      if (!str.includes(`&lt;span class="mention" data-mention="${match}"&gt;`)) {
        // If not, surround it with the span element
        return `&lt;span class="mention" data-mention="${match}"&gt;${match}&lt;/span&gt;`;
      }
      return match;
    });
    return newStr;
}

document.addEventListener(EditorEvent.EDITOR__CREATE, onCreate);
