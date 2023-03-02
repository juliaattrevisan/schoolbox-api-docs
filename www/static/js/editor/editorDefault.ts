/**
 * To create a default CKEditor instances
 */

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import { getDefault, EditorDefaultConfig } from "@js/editor/config/configDefault";
import CKEditorInspector from "@ckeditor/ckeditor5-inspector";
import { insertElementAfter, injectScript } from "@js/util/domUtils";
import "@js/editor/styles/editor.css"; // to overwrite some of the styles

/**
 * Add the word count UI to the bottom of the editor
 * Its assumed this method is called once the editor has been created,
 * so editorTextArea.nextSibling will be the newly created editor instance
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor has replaced
 * @param { CKEditor } editor - the CKEditor instance
 */
function insertWordCount(textarea: HTMLTextAreaElement, editor) {
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
 * Add the editor to the global scope so it can be accessed
 * in other twig files etc
 *
 * @param { CKEditor } editor - the CKEditor instance
 * @param { string } elementId - the id to reference the editor by
 */
function addEditorToGlobalScope(editor, elementId) {
    window.sbProps.editorInstances[elementId] = editor;
}

/**
 * Add the iFramely embed code in order for iframe embeds to work
 * For more info: https://iframely.com/docs/ckeditor
 */
async function addIframelyEmbed() {
    await injectScript("//cdn.iframe.ly/embed.js?cancel=0");
}

/**
 * Add in any additional setup required and then dispatch that the editor is ready
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @param { CKEditor } editor - the CKEditor instance
 */
async function onEditorCreated(textarea: HTMLTextAreaElement, editor) {
    insertWordCount(textarea, editor);
    removeValidation();
    addEditorToGlobalScope(editor, textarea.id);
    await addIframelyEmbed();
    CKEditorInspector.attach(editor);
    document.dispatchEvent(
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
