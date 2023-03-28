import { EditorEvent } from "@js/editor/event/editorEvent";
import { EditorConfig } from "@js/editor/types/editorTypes";
import { surroundUnsurroundedTokens } from "@js/editor/settings/tokensSettings";

/**
 * This is called when an editor is created, it checks the data for any tokens that need to be surrounded
 * and then adds an event listener to allow pages to run the same check when they need to
 *
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @param { EditorConfig } config - the config to use when creating the editor
 */
export function initCheckData(textarea: HTMLTextAreaElement, config: EditorConfig) {
    if (config.hasTokens) {
        textarea.innerHTML = surroundUnsurroundedTokens(textarea.innerHTML, config);
    }

    textarea.addEventListener(EditorEvent.EDITOR__CHECK_DATA, onEditorCheckData);
}

/**
 * This function can be called by other pages to run the surroundUnsurroundedTokens function on the editor,
 * this might happen if a page has changed the data in the form with .setData and needs to re-run the function
 *
 * @param { CustomEvent } event - the custom event
 */
function onEditorCheckData(event: CustomEvent) {
    const editor = window.sbProps.editors[event.target.id || event.target.name];

    if (editor.config.get("sbConfig").hasTokens) {
        editor.setData(surroundUnsurroundedTokens(editor.getData(), editor.config.get("sbConfig")));
    }
}
