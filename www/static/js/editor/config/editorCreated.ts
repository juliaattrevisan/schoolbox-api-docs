/**
 * Once the editor has been created, we can add in any additional setup required
 */

import { Editor, EditorConfig, EditorToolbar } from "@js/editor/types/editorTypes";
import { insertWordCount } from "@js/editor/settings/wordCountSettings";

/**
 * Add the editor to the global scope so it can be accessed
 * in other twig files etc
 * @param { Editor } editor - the CKEditor instance
 * @param { HTMLTextAreaElement | HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 */
function addToGlobalScope(editor: Editor, sourceElement: HTMLTextAreaElement | HTMLElement) {
    if (sourceElement.id) {
        window.sbProps.editors[sourceElement.id] = editor;
    } else if (sourceElement instanceof HTMLTextAreaElement && sourceElement.name) {
        window.sbProps.editors[sourceElement.name] = editor;
    }
}

/**
 * For the simple editor, we need to disable the drag and drop
 * and the ability for the editor to auto embed media
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
 * Once the iFramely script has loaded, we can add in the event listener
 * @param { Editor } editor - the CKEditor instance
 */
function configureIframelyListener(editor: Editor) {
    /**
     * Track copy/paste to add an extra undo step if the link fails to unfurl
     */
    let isCopied = false;
    editor.editing.view.document.on("clipboardInput", () => {
        isCopied = true;
    });
    /**
     * If the link fails to unfurl and be presented in the editor as an embed,
     * we should pull out the embed attempt and just add a link to the failed url instead
     * See https://iframely.com/docs/embedjs - "Act when unfurling fails" for further details
     * We can use the undo tool to undo the embed action
     */
    window.iframely.on("cancel", (url: string) => {
        // there can be multiple editors on a page, so need to ensure we have the right editor, as each will listen for the dispatch event
        if (editor.editing.view.hasDomSelection) {
            // the first undo removed the media embed
            editor.execute("undo");
            // and if copy/paste undo again removes the copied text added to the editor
            if (isCopied) {
                editor.execute("undo");
                isCopied = false;
            }
            // and finally add the link
            editor.execute("link", url);
        }
    });
}

/**
 * Add the inspector to the editor to help with debugging
 * in development mode: https://ckeditor.com/docs/ckeditor5/latest/framework/development-tools.html
 * The process.env.NODE_ENV is built into node/webpack:
 * https://webpack.js.org/configuration/mode/
 * so it will only be imported in development mode, not in production
 * No longer used, but kept for any future debugging needs
 * @param { Editor } editor - the CKEditor instance
 */
// async function attachInspector(editor: Editor) {
//     if (process.env.NODE_ENV === "development") {
//         const inspector = await import("@ckeditor/ckeditor5-inspector");
//         inspector.default.attach(editor);
//     }
// }

/**
 * Add in any additional setup required and then dispatch that the editor is ready
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { Editor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 */
export function modifyEditor(sourceElement: HTMLElement, editor: Editor, config: EditorConfig) {
    addToGlobalScope(editor, sourceElement);
    insertWordCount(sourceElement, editor, config);
    disableAutoMedia(editor, config);
    removeValidation();
    configureIframelyListener(editor);
}
