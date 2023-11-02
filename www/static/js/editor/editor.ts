/**
 * To create a default CKEditor instances
 */

import { ClassicEditor } from "@ckeditor/ckeditor5-editor-classic";
import type { Editor } from "@ckeditor/ckeditor5-core";
import { getConfig } from "@js/editor/config/editorConfig";
import { EditorConfig, EditorToolbar } from "@js/editor/types/editorTypes";
import { EditorDeleteEventInfo, EditorEvent } from "@js/editor/event/editorEvent";
import { initCheckData } from "@js/editor/config/editorCheckData";
import { modifyEditor } from "@js/editor/config/editorCreated";
import "@js/editor/styles/editor.css"; // to overwrite some of the styles

/**
 * Add in any additional setup required and then dispatch that the editor is ready
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { Editor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 */
async function onCreated(sourceElement: HTMLElement, editor: Editor, config: EditorConfig) {
    await modifyEditor(sourceElement, editor, config);
    sourceElement.dispatchEvent(
        new CustomEvent(EditorEvent.EDITOR__CREATED, {
            detail: {
                editor,
                sourceElement,
            },
        })
    );
}

/**
 * set up any default config when it's not been defined
 * @param { EditorConfig } config - the config to use when creating the editor
 */
function addDefaultConfig(config: EditorConfig) {
    if (config.toolbar === undefined) {
        config.toolbar = EditorToolbar.DEFAULT;
    }
    if (config.maxCharCount === undefined) {
        config.maxCharCount = window.sbProps.MAX_BYTES_MYSQL_TEXT;
    }
}

/**
 * create the ckeditor instance
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { EditorConfig } config - the config to use when creating the editor
 */
function create(sourceElement: HTMLElement, config: EditorConfig) {
    addDefaultConfig(config);
    ClassicEditor.create(sourceElement, getConfig(config))
        .then((editor) => {
            onCreated(sourceElement, editor, config);
        })
        .catch((error) => {
            console.error(error.stack);
        });
}

/**
 * Listen for init events to create any instances
 * @param { CustomEvent } event - the custom event
 */
function onCreate(event: CustomEvent) {
    const config = event.detail as EditorConfig;
    const { targetElements } = config;
    targetElements.forEach((targetElement) => {
        // assume that each targetElement could contain multiple instances
        document.querySelectorAll(targetElement).forEach((sourceElement) => {
            // Sometimes the targetElement selector may match elements that are not intended for a CKEditor,
            // for example, if "[data-cke]" is used, it will match the CKEditor <style> element,
            // this results in a sanitized style element, breaking various editor styles.
            //
            // To solve this, we check that the element is within the set of allowed CKEditor elements
            const allowedElements = ["TEXTAREA", "DIV", "ARTICLE"];
            if (!allowedElements.includes(sourceElement.tagName.toUpperCase())) {
                console.warn(
                    `CKEditor: Element "${sourceElement.tagName}" is not allowed to be replaced by CKEditor.` +
                        ` Targeted element by selector "${targetElement}":`,
                    sourceElement
                );
                return;
            }
            initCheckData(sourceElement as HTMLElement, config);
            create(sourceElement as HTMLElement, config);
        });
    });
}

/**
 * Respond to a request to delete an editor instance, by deleting the editor instance, and then firing another event
 * informing that an editor instance has been deleted.
 * @param { CustomEvent } event - Information about the event.
 */
function onDelete(event: CustomEvent) {
    const eventDetail = event.detail as EditorDeleteEventInfo;
    const sourceElement = eventDetail.editor.sourceElement as HTMLTextAreaElement;

    const keyOfEditorToDelete =
        sourceElement.id !== undefined
            ? sourceElement.id
            : sourceElement.name !== undefined
            ? sourceElement.name
            : null;

    if (keyOfEditorToDelete !== null) {
        if (window.sbProps.editors[keyOfEditorToDelete] === undefined) {
            throw new Error('Editor with key "' + keyOfEditorToDelete + '" does not exist in window.sbProps.editors.');
        }

        window.sbProps.editors[keyOfEditorToDelete].destroy();
        delete window.sbProps.editors[keyOfEditorToDelete];

        document.dispatchEvent(
            new CustomEvent("EDITOR__DELETED", {
                detail: {
                    keyOfDeletedEditor: keyOfEditorToDelete,
                    containingFormId: "lesson-plan-template-form",
                    submitButtons: [document.getElementById("save-button")],
                },
            })
        );
    }
}

document.addEventListener(EditorEvent.EDITOR__CREATE, onCreate);
document.addEventListener(EditorEvent.EDITOR__DELETE, onDelete);
