/**
 * Once the editor has been created, we can add in any additional setup required
 */
import { EditorToolbar } from "@js/editor/types/editorTypes";
import type { SbxEditor, SbxEditorConfig, SbxEditorElement } from "@js/editor/types/editorTypes";
import { insertWordCount } from "@js/editor/settings/wordCountSettings";
import { getSourceElementIdentifier } from "./editorCheckData";

/**
 * Add the editor to the global scope so it can be accessed
 * in other twig files etc
 * @param editor - the CKEditor instance
 * @param sourceElement - the sourceElement the CKEditor will replace
 */
function addToGlobalScope(editor: SbxEditor, sourceElement: SbxEditorElement): void {
    const editorIdentifier = getSourceElementIdentifier(sourceElement);
    if (editorIdentifier === null)
        throw new Error("Editor must have an ID or name to be accessible in the global scope");

    window.sbProps.editors[editorIdentifier] = editor;
}

/**
 * CKEditor doesn't automatically check the readonly attribute on the source element,
 * so we do it here instead.
 * @param editor - the CKEditor instance
 * @param sourceElement - the sourceElement the CKEditor will replace
 */
function checkReadonly(editor: SbxEditor, sourceElement: SbxEditorElement): void {
    if (sourceElement instanceof HTMLTextAreaElement && sourceElement.readOnly) {
        editor.enableReadOnlyMode("readonly");
    }
}

/**
 * Mirror editor change events back to the source element if enabled in the config
 * Sometimes the editor is used in a form which pays attention to the base <textarea> element for autosave/etc
 * in this case, we need to dispatch the change event from the editor back to the source element
 * @param editor - the CKEditor instance
 * @param config - the CKEditor config object
 */
function mirrorEditorChanges(editor: SbxEditor, config: SbxEditorConfig): void {
    if (config.mirrorChangeEvents && !editor.isReadOnly) {
        editor.model.document.on("change", () => {
            if (!(editor.sourceElement instanceof HTMLTextAreaElement)) return;
            editor.sourceElement.value = editor.getData();
            editor.sourceElement.dispatchEvent(new Event("change", { bubbles: true }));
        });
    }
}

/**
 * For the simple editor, we need to disable the drag and drop
 * and the ability for the editor to auto embed media
 * @param editor - the CKEditor instance
 * @param config - the CKEditor config object
 */
function disableAutoMedia(editor: SbxEditor, config: SbxEditorConfig): void {
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
function removeValidation(): void {
    $("form").each(() => {
        // @ts-expect-error - ignore the error as we're using jquery
        if ($(this).data("validator")) {
            // @ts-expect-error - ignore the error as we're using jquery
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access -- see above
            $(this).data("validator").settings.ignore = ".ck";
        }
    });
}

/**
 * Once the iFramely script has loaded, we can add in the event listener
 * @param editor - the CKEditor instance
 */
function configureIframelyListener(editor: SbxEditor): void {
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
 * Add in any additional setup required and then dispatch that the editor is ready
 * @param sourceElement - the sourceElement the CKEditor will replace
 * @param editor - the CKEditor instance
 * @param config - the CKEditor config object
 */
export function modifyEditor(sourceElement: SbxEditorElement, editor: SbxEditor, config: SbxEditorConfig): void {
    addToGlobalScope(editor, sourceElement);
    checkReadonly(editor, sourceElement);
    mirrorEditorChanges(editor, config);
    insertWordCount(sourceElement, editor, config);
    disableAutoMedia(editor, config);
    removeValidation();
    configureIframelyListener(editor);
}
