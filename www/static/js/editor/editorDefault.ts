/**
 * To create a default CKEditor instances
 */

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import { getDefault } from "@js/editor/config/configDefault";
import CKEditorInspector from "@ckeditor/ckeditor5-inspector";
import { insertElementAfter } from "@js/util/domUtils";
import "@js/editor/styles/editor.css"; // to overwrite some of the styles

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
 * create the ckeditor instance
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 */
function createEditor(textarea: HTMLTextAreaElement) {
    ClassicEditor.create(textarea, getDefault())
        .then((editor) => {
            insertWordCount(textarea, editor);
            removeValidation();
            CKEditorInspector.attach(editor);
            document.dispatchEvent(
                new CustomEvent("EDITOR__CREATED", {
                    detail: {
                        editor: editor,
                        textarea: textarea,
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
function onCreate(event: CustomEvent) {
    const targetElements = event.detail.targetElements as string[];
    targetElements.forEach((targetElement) => {
        // assume that each targetElement could contain multiple instances
        document.querySelectorAll(targetElement).forEach((textarea) => {
            createEditor(textarea as HTMLTextAreaElement);
        });
    });
}

document.addEventListener("EDITOR__CREATE", onCreate);
