/**
 * Misc editor utils that can be used by any editor type
 */

import { insertElementAfter, injectScript } from "@js/util/domUtils";

/**
 * Add the word count UI to the bottom of the editor
 * Its assumed this method is called once the editor has been created,
 * so editorTextArea.nextSibling will be the newly created editor instance
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor has replaced
 * @param { CKEditor } editor - the CKEditor instance
 */
export function insertWordCount(textarea: HTMLTextAreaElement, editor) {
    const wordCountPlugin = editor.plugins.get("WordCount");
    insertElementAfter(textarea.nextSibling, wordCountPlugin.wordCountContainer);
}

/**
 * Prevent jquery.validator trying to access ckeditor instances
 * as it throws an error on focus in / out
 *
 * @todo - review this, there may be a better way to handle this
 */
export function removeValidation() {
    $("form").each(function () {
        if ($(this).data("validator")) {
            $(this).data("validator").settings.ignore = ".ck";
        }
    });
}

/**
 * Add the iFramely embed code in order for iframe embeds to work
 * For more info: https://iframely.com/docs/ckeditor
 */
export async function addIframelyEmbed() {
    await injectScript("//cdn.iframe.ly/embed.js?cancel=0");
}
