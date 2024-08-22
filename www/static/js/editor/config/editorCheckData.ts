import { EditorEvent } from "@js/editor/event/editorEvent";
import { SbxEditorConfig, SbxEditorElement } from "@js/editor/types/editorTypes";
import { addRelToNewTabLinks, addPrefixToElementIDs } from "@js/editor/settings/linkSettings";
import { addFullWidthAttributes, removeHeightFromVideoThumbs } from "@js/editor/settings/imageSettings";
import { addFullWidthStyle, addAlignLeftStyle } from "@js/editor/settings/tableSettings";
import { wrapTokens } from "@js/editor/settings/tokensSettings";

/**
 * Get the identifier for a given editor element (The ID, if it exists, otherwise the name if it's a textarea)
 * @param { HTMLElement } sourceElement - the element to get the identifier for
 * @returns { string | null } - the identifier for the element, or null if it doesn't have one
 */
export function getSourceElementIdentifier(sourceElement: HTMLElement): string | null {
    if (sourceElement instanceof HTMLElement && sourceElement.id) {
        return sourceElement.id;
    }
    if (sourceElement instanceof HTMLTextAreaElement && sourceElement.name) {
        return sourceElement.name;
    }
    return null;
}

/**
 * Parse the content as HTML so we can run .querySelectorAll etc on it
 * @param { SbxEditorElement } sourceElement - the sourceElement the CKEditor will replace
 * @returns { HTMLElement } - the sourceElement's inner HTML content
 */
function getSourceElementHTML(sourceElement: HTMLElement): HTMLElement {
    // if the sourceElement is a textarea, use .sourceElementContent as .innerHTML will return HTML as escaped text
    const sourceElementContent =
        sourceElement instanceof HTMLTextAreaElement ? sourceElement.value : sourceElement.innerHTML;
    const parser = new DOMParser();
    return parser.parseFromString(sourceElementContent, "text/html").body;
}

/**
 * Run through any checks required on the data before it's loaded into the editor
 * @param { HTMLElement } sourceElementHTML - the contents of the editor as HTML, wrapped in any HTMLElement (e.g. a DOMparsed editor.getData())
 * @param { SbxEditorConfig } config - the config to use when creating the editor
 * @returns { HTMLElement } - the sourceElement content as HTML
 */
function runChecks(sourceElementHTML: HTMLElement, config: SbxEditorConfig): HTMLElement {
    let newSourceElementHTML = addRelToNewTabLinks(sourceElementHTML);
    newSourceElementHTML = addPrefixToElementIDs(newSourceElementHTML);
    newSourceElementHTML = addFullWidthAttributes(newSourceElementHTML);
    newSourceElementHTML = removeHeightFromVideoThumbs(newSourceElementHTML);
    newSourceElementHTML = addFullWidthStyle(newSourceElementHTML);
    newSourceElementHTML = addAlignLeftStyle(newSourceElementHTML);
    if (config.hasTokens) {
        newSourceElementHTML = wrapTokens(newSourceElementHTML, config);
    }
    return newSourceElementHTML;
}

/**
 * This function can be called by other pages to run the wrapTokens function on the editor,
 * this might happen if a page has changed the data in the form with .setData and needs to re-run the function
 * It's currently only called within the Add Pastoral Record page, where it only needs to check
 * for tokens when a pastoral type or sub-type change is triggered.
 * @param { HTMLElement } targetElement - the source element of the CKEditor to run checks for
 */
function onEditorCheckData(targetElement: HTMLElement) {
    const editorIdentifier = getSourceElementIdentifier(targetElement);
    const editor = editorIdentifier ? window.sbProps.editors[editorIdentifier] ?? null : null;

    if (editor === null) throw new Error("onEditorCheckData: Unable to find editor in window.sbProps.editors");

    const editorConfig = editor.config.get("sbConfig") as SbxEditorConfig;
    if (editorConfig.hasTokens) {
        const parser = new DOMParser();
        const sourceElementHTML = parser.parseFromString(editor.getData(), "text/html").body;
        editor.setData(runChecks(sourceElementHTML, editorConfig).innerHTML);
    }
}

/**
 * This function is called when the editor is created, it checks the data and manipulates as required
 * @param { SbxEditorElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { SbxEditorConfig } config - the config to use when creating the editor
 */
export function initCheckData(sourceElement: SbxEditorElement, config: SbxEditorConfig) {
    const sourceElementHTML = getSourceElementHTML(sourceElement);
    const newSourceElementHTML = runChecks(sourceElementHTML, config);
    sourceElement.innerHTML = newSourceElementHTML.innerHTML;
    // ensure both .innerHTML and .value are kept consistent
    if (sourceElement instanceof HTMLTextAreaElement) {
        sourceElement.value = newSourceElementHTML.innerHTML;
    }
    // Adds an event listener to allow pages to run the same check when they need to
    sourceElement.addEventListener(EditorEvent.EDITOR__CHECK_DATA, (event) => {
        const eventTarget = (event as CustomEvent).target;
        if (!(eventTarget instanceof HTMLElement)) throw new Error("initCheckData: Event target is not an HTMLElement");
        onEditorCheckData(eventTarget);
    });
}
