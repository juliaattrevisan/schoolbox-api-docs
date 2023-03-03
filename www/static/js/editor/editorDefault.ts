/**
 * To create a default CKEditor instances
 */

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import { getDefault } from "@js/editor/config/configDefault";
import { EditorDefaultConfig } from "@js/editor/types/editorTypes";
import { insertWordCount, removeValidation, addIframelyEmbed } from "@js/editor/util/editorUtils";
import CKEditorInspector from "@ckeditor/ckeditor5-inspector";
import "@js/editor/styles/editor.css"; // to overwrite some of the styles

/**
 * Add in any additional setup required and then dispatch that the editor is ready
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @param { CKEditor } editor - the CKEditor instance
 */
async function onEditorCreated(textarea: HTMLTextAreaElement, editor) {
    insertWordCount(textarea, editor);
    removeValidation();
    await addIframelyEmbed();
    CKEditorInspector.attach(editor);
    textarea.dispatchEvent(
        new CustomEvent("EDITOR__CREATED", {
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
 * @param { EditorDefaultConfig } config - the config to use when creating the editor
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 */
function createEditor(textarea: HTMLTextAreaElement, config: EditorDefaultConfig) {
    ClassicEditor.create(textarea, getDefault(config))
        .then((editor) => {
            onEditorCreated(textarea, editor);
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
    const config = event.detail as EditorDefaultConfig;
    const { targetElements } = config;
    targetElements.forEach((targetElement) => {
        // assume that each targetElement could contain multiple instances
        document.querySelectorAll(targetElement).forEach((textarea) => {
            createEditor(textarea as HTMLTextAreaElement, config);
        });
    });
}

document.addEventListener("EDITOR__CREATE", onCreate);
