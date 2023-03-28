/**
 * Once the editor has been created, we can add in any additional setup required
 */

import type { Editor } from "@ckeditor/ckeditor5-core";
import { EditorConfig, EditorToolbar } from "@js/editor/types/editorTypes";
import { injectScript, isScriptAdded } from "@js/util/domUtils";
import { insertWordCount } from "@js/editor/settings/wordCountSettings";

/**
 * Add the editor to the global scope so it can be accessed
 * in other twig files etc
 *
 * @param { Editor } editor - the CKEditor instance
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 */
function addToGlobalScope(editor: Editor, textarea: HTMLTextAreaElement) {
    if (textarea.id) {
        window.sbProps.editors[textarea.id] = editor;
    } else if (textarea.name) {
        window.sbProps.editors[textarea.name] = editor;
    }
}

/**
 * For the simple editor, we need to disable the drag and drop
 * and the ability for the editor to auto embed media
 *
 * @param { Editor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 */
function disableAutoMedia(editor: Editor, config: EditorConfig) {
    if (config.toolbar === EditorToolbar.SIMPLE) {
        editor.plugins.get("DragDrop").forceDisabled("disableDragDrop");
        editor.plugins.get("AutoMediaEmbed").destroy();
    }
}

/**
 * Prevent jquery.validator trying to access ckeditor instances
 * as it throws an error on focus in / out
 *
 * @todo - review this, there may be a better way to handle this
 */
function removeValidation() {
    $("form").each(function () {
        if ($(this).data("validator")) {
            $(this).data("validator").settings.ignore = ".ck";
        }
    });
}

/**
 * Add the iFramely embed code in order for iframe embeds to work
 * For more info: https://iframely.com/docs/ckeditor
 *
 * @param { EditorConfig } config - the CKEditor config object
 * @param { Editor } editor - the CKEditor instance
 */
async function addIframelyEmbed(config: EditorConfig, editor: Editor) {
    if (config.toolbar === EditorToolbar.DEFAULT || config.toolbar === EditorToolbar.COMMENT) {
        const script = "//cdn.iframe.ly/embed.js?cancel=0";
        if (!isScriptAdded(script)) {
            await injectScript(script);
        }
        /**
         * If the link fails to unfurl and be presented in the editor as an embed,
         * we should pull out the embed attempt and just add a link to the failed url instead
         * See https://iframely.com/docs/embedjs - "Act when unfurling fails" for further details
         * We can use the undo tool to undo the embed action
         */
        window.iframely.on("cancel", (url: string) => {
            editor.execute("undo");
            editor.execute("link", url);
        });
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
async function attachInspector(editor: Editor) {
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
    addToGlobalScope(editor, textarea);
    // addSBConfigToEditor(editor, config);
    insertWordCount(textarea, editor, config);
    disableAutoMedia(editor, config);
    removeValidation();
    await addIframelyEmbed(config, editor);
    await attachInspector(editor);
}
