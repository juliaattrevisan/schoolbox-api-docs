/**
 * To create a default CKEditor instances
 */

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import { getDefault } from "@js/editor/configDefault";
import CKEditorInspector from "@ckeditor/ckeditor5-inspector";
import { insertElementAfter } from "@js/util/domUtils";

/**
 * Add the word count UI to the bottom of the editor
 * Its assumed this method is called once the editor has been created,
 * so editorTextArea.nextSibling will be the newly created editor instance
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor has replaced
 * @param { CKEditor } editor - the CKEditor instance
 */
function insertWordCount(textarea, editor) {
    const wordCountPlugin = editor.plugins.get("WordCount");
    insertElementAfter(textarea.nextSibling, wordCountPlugin.wordCountContainer);
}

/**
 * create the ckeditor instance
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 */
function createEditor(textarea: HTMLTextAreaElement) {
    ClassicEditor.create(textarea, getDefault())
        .then((editor) => {
            insertWordCount(textarea, editor);
            CKEditorInspector.attach(editor);
            document.dispatchEvent(
                new CustomEvent("EDITOR__CREATED", {
                    detail: {
                        editor: editor,
                    },
                })
            );
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
function onInit(event: CustomEvent) {
    const targetElements = event.detail.targetElements as string[];
    targetElements.forEach((targetElement) => {
        // assume that each targetElement could contain multiple instances
        document.querySelectorAll(targetElement).forEach((textarea) => {
            createEditor(textarea as HTMLTextAreaElement);
        });
    });
}

document.addEventListener("EDITOR__CREATE", onInit);
