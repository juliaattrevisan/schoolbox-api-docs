import { EditorEvent } from "@js/editor/event/editorEvent";
import { EditorConfig } from "@js/editor/types/editorTypes";
import { addRelToNewTabLinks } from "@js/editor/settings/linkSettings";
import { addFullWidthAttributes } from "@js/editor/settings/imageSettings";
import { surroundUnsurroundedTokens } from "@js/editor/settings/tokensSettings";

/**
 * Parse the content as HTML so we can run .querySelectorAll etc on it
 *
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @returns { HTMLElement } - the sourceElement content as HTML
 */
function getSourceElementHTML(sourceElement: HTMLElement) {
    // if the sourceElement is a textarea, use .sourceElementContent as .innerHTML will return HTML as escaped text
    const sourceElementContent = (sourceElement as HTMLTextAreaElement).value
        ? (sourceElement as HTMLTextAreaElement).value
        : sourceElement.innerHTML;
    const parser = new DOMParser();
    return parser.parseFromString(sourceElementContent, "text/html").body;
}

/**
 * This function is called when the editor is created, it checks the data and manipulates as required
 *
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { EditorConfig } config - the config to use when creating the editor
 */
export function initCheckData(sourceElement: HTMLElement, config: EditorConfig) {
    let sourceElementHTML = getSourceElementHTML(sourceElement);
    // Add the rel attribute to any links that have the target attribute set to _blank
    sourceElementHTML = addRelToNewTabLinks(sourceElementHTML);
    // Add the data-schoolboxresource-fullwidth attribute to any images that have the class full-width
    sourceElementHTML = addFullWidthAttributes(sourceElementHTML);
    // Checks the data for any tokens that need to be surrounded
    sourceElementHTML = surroundUnsurroundedTokens(sourceElementHTML, config);
    sourceElement.innerHTML = sourceElementHTML.innerHTML;
    // ensure both .innerHTML and .value are kept consistent
    if ((sourceElement as HTMLTextAreaElement).value) {
        (sourceElement as HTMLTextAreaElement).value = sourceElementHTML.innerHTML;
    }
    // Adds an event listener to allow pages to run the same check when they need to
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
        editor.setData(
            surroundUnsurroundedTokens(getSourceElementHTML(editor.sourceElement), editor.config.get("sbConfig"))
                .innerHTML
        );
    }
}
