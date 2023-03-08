/**
 * To create a default CKEditor instances
 */

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import { getConfig } from "@js/editor/config/editorConfig";
import { EditorConfig } from "@js/editor/types/editorTypes";
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
 * @param { CKEditor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 */
async function onCreated(textarea: HTMLTextAreaElement, editor, config: EditorConfig) {
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
            create(textarea as HTMLTextAreaElement, config);
        });
    });
}

document.addEventListener(EditorEvent.EDITOR__CREATE, onCreate);
