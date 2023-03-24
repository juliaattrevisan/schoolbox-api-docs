/**
 * To create a default CKEditor instances
 */

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import type { Editor } from "@ckeditor/ckeditor5-core";
import { getConfig } from "@js/editor/config/editorConfig";
import { EditorConfig, EditorToolbar } from "@js/editor/types/editorTypes";
import { EditorEvent } from "@js/editor/event/editorEvent";
import { initCheckData } from "@js/editor/config/editorCheckData";
import { modifyEditor } from "@js/editor/config/editorCreated";
import "@js/editor/styles/editor.css"; // to overwrite some of the styles

/**
 * Add in any additional setup required and then dispatch that the editor is ready
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @param { Editor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 */
async function onCreated(textarea: HTMLTextAreaElement, editor: Editor, config: EditorConfig) {
    await modifyEditor(textarea, editor, config);
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
 * set up any default config when it's not been defined
 *
 * @param { EditorConfig } config - the config to use when creating the editor
 */
function addDefaultConfig(config: EditorConfig) {
    if (config.toolbar === undefined) {
        config.toolbar = EditorToolbar.DEFAULT;
    }
    if (config.maxCharCount === undefined) {
        config.maxCharCount = 65_000;
    }
}

/**
 * create the ckeditor instance
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @param { EditorConfig } config - the config to use when creating the editor
 */
function create(textarea: HTMLTextAreaElement, config: EditorConfig) {
    addDefaultConfig(config);
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
            initCheckData(textarea as HTMLTextAreaElement, config);
            create(textarea as HTMLTextAreaElement, config);
        });
    });
}

document.addEventListener(EditorEvent.EDITOR__CREATE, onCreate);
