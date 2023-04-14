import { EditorEvent } from "@js/editor/event/editorEvent";
import { EditorConfig } from "@js/editor/types/editorTypes";
import { addRelToNewTabLinks } from "@js/editor/settings/linkSettings";
import { addFullWidthAttributes } from "@js/editor/settings/imageSettings";
import { surroundUnsurroundedTokens } from "@js/editor/settings/tokensSettings";

/**
 * This function is called when the editor is created, it checks the data and manipulates as required
 *
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { EditorConfig } config - the config to use when creating the editor
 */
export function initCheckData(sourceElement: HTMLElement, config: EditorConfig) {
    // Add the rel attribute to any links that have the target attribute set to _blank
    sourceElement.innerHTML = addRelToNewTabLinks(sourceElement);
    // Add the data-schoolboxresource-fullwidth attribute to any images that have the class full-width
    sourceElement.innerHTML = addFullWidthAttributes(sourceElement);
    /**
     * This is called when an editor is created, it checks the data for any tokens that need to be surrounded
     * and then adds an event listener to allow pages to run the same check when they need to
     */
    if (config.hasTokens) {
        sourceElement.innerHTML = surroundUnsurroundedTokens(sourceElement.innerHTML, config);
    }
    sourceElement.addEventListener(EditorEvent.EDITOR__CHECK_DATA, onEditorCheckData);
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
