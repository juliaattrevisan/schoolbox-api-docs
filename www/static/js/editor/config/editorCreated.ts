/**
 * Once the editor has been created, we can add in any additional setup required
 */

import type { Editor } from "@ckeditor/ckeditor5-core";
import { EditorConfig, EditorToolbar } from "@js/editor/types/editorTypes";
import { insertElementAfter, injectScript, isScriptAdded } from "@js/util/domUtils";

/**
 * Add the word count UI to the bottom of the editor
 * Its assumed this method is called once the editor has been created,
 * so editorTextArea.nextSibling will be the newly created editor instance
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor has replaced
 * @param { Editor } editor - the CKEditor instance
 */
export function insertWordCount(textarea: HTMLTextAreaElement, editor: Editor) {
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
    const script = "//cdn.iframe.ly/embed.js?cancel=0";
    if (!isScriptAdded(script)) {
        await injectScript(script);
    }
}

/**
 * Add the editor to the global scope so it can be accessed
 * in other twig files etc
 *
 * @param { Editor } editor - the CKEditor instance
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 */
export function addToGlobalScope(editor: Editor, textarea: HTMLTextAreaElement) {
    if (textarea.id) {
        window.sbProps.editors[textarea.id] = editor;
    } else if (textarea.name) {
        window.sbProps.editors[textarea.name] = editor;
    }
}

/**
 * Add the inspector to the editor to help with debugging
 * in development mode: https://ckeditor.com/docs/ckeditor5/latest/framework/development-tools.html
 * The process.env.NODE_ENV is built into node/webpack:
 * https://webpack.js.org/configuration/mode/
 * so it will only be imported in development mode, not in production
 *
 * @param { Editor } editor - the CKEditor instance
 */
export async function attachInspector(editor: Editor) {
    if (process.env.NODE_ENV === "development") {
        const inspector = await import("@ckeditor/ckeditor5-inspector");
        inspector.default.attach(editor);
    }
}

/**
 * Add in any additional setup required and then dispatch that the editor is ready
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @param { Editor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 */
export async function modifyEditor(textarea: HTMLTextAreaElement, editor: Editor, config: EditorConfig) {
    insertWordCount(textarea, editor);
    removeValidation();
    addToGlobalScope(editor, textarea);
    editor.config.sbConfig = config;
    attachInspector(editor);
    if (config.toolbar === EditorToolbar.SIMPLE) {
        editor.plugins.get("DragDrop").forceDisabled("disableDragDrop");
        editor.plugins.get("AutoMediaEmbed").destroy();
    } else {
        await addIframelyEmbed();
    }
}
