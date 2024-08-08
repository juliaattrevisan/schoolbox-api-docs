import { EditorEvent } from "@js/editor/event/editorEvent";
import { EditorConfig } from "@js/editor/types/editorTypes";
import { addRelToNewTabLinks, addPrefixToElementIDs } from "@js/editor/settings/linkSettings";
import { addFullWidthAttributes, removeHeightFromVideoThumbs } from "@js/editor/settings/imageSettings";
import { addFullWidthStyle, addAlignLeftStyle } from "@js/editor/settings/tableSettings";
import { wrapTokens } from "@js/editor/settings/tokensSettings";

/**
 * Parse the content as HTML so we can run .querySelectorAll etc on it
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @returns { HTMLElement } - the sourceElement content as HTML
 */
function getSourceElementHTML(sourceElement: HTMLElement): HTMLElement {
    // if the sourceElement is a textarea, use .sourceElementContent as .innerHTML will return HTML as escaped text
    const sourceElementContent = (sourceElement as HTMLTextAreaElement).value
        ? (sourceElement as HTMLTextAreaElement).value
        : sourceElement.innerHTML;
    const parser = new DOMParser();
    return parser.parseFromString(sourceElementContent, "text/html").body;
}

/**
 * Run through any checks required on the data before it's loaded into the editor
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { EditorConfig } config - the config to use when creating the editor
 * @returns { HTMLElement } - the sourceElement content as HTML
 */
function runChecks(sourceElement: HTMLElement, config: EditorConfig): HTMLElement {
    let sourceElementHTML = getSourceElementHTML(sourceElement);
    sourceElementHTML = addRelToNewTabLinks(sourceElementHTML);
    sourceElementHTML = addPrefixToElementIDs(sourceElementHTML);
    sourceElementHTML = addFullWidthAttributes(sourceElementHTML);
    sourceElementHTML = removeHeightFromVideoThumbs(sourceElementHTML);
    sourceElementHTML = addFullWidthStyle(sourceElementHTML);
    sourceElementHTML = addAlignLeftStyle(sourceElementHTML);
    if (config.hasTokens) {
        sourceElementHTML = wrapTokens(sourceElementHTML, config);
    }
    return sourceElementHTML;
}

/**
 * This function can be called by other pages to run the wrapTokens function on the editor,
 * this might happen if a page has changed the data in the form with .setData and needs to re-run the function
 * It's currently only called within the Add Pastoral Record page, where it only needs to check
 * for tokens when a pastoral type or sub-type change is triggered.
 * @param { CustomEvent } event - the custom event
 */
function onEditorCheckData(event: CustomEvent) {
    const eventTarget = event.target;
    if (eventTarget === null) throw new Error("onEditorCheckData: Event target is null");

    let editor: (typeof window.sbProps.editors)[string] | null = null;
    if (eventTarget instanceof HTMLElement && eventTarget.id) {
        editor = window.sbProps.editors[eventTarget.id] ?? null;
    } else if (eventTarget instanceof HTMLTextAreaElement && eventTarget.name) {
        editor = window.sbProps.editors[eventTarget.name] ?? null;
    }

    if (editor === null) throw new Error("onEditorCheckData: Unable to find editor in window.sbProps.editors");

    const editorConfig = editor.config.get("sbConfig") as EditorConfig;
    if (editorConfig.hasTokens) {
        const parser = new DOMParser();
        const sourceElementHTML = parser.parseFromString(editor.getData(), "text/html").body;
        editor.setData(runChecks(sourceElementHTML, editorConfig).innerHTML);
    }
}

/**
 * This function is called when the editor is created, it checks the data and manipulates as required
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { EditorConfig } config - the config to use when creating the editor
 */
export function initCheckData(sourceElement: HTMLElement, config: EditorConfig) {
    const sourceElementHTML = runChecks(sourceElement, config);
    sourceElement.innerHTML = sourceElementHTML.innerHTML;
    // ensure both .innerHTML and .value are kept consistent
    if ((sourceElement as HTMLTextAreaElement).value) {
        (sourceElement as HTMLTextAreaElement).value = sourceElementHTML.innerHTML;
    }
    // Adds an event listener to allow pages to run the same check when they need to
    sourceElement.addEventListener(EditorEvent.EDITOR__CHECK_DATA, (event) => {
        onEditorCheckData(event as CustomEvent);
    });
}
