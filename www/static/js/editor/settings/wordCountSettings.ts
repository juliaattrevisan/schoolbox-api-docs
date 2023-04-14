import type { Editor } from "@ckeditor/ckeditor5-core";
import { EditorConfig } from "@js/editor/types/editorTypes";
import { insertElementAfter } from "@js/util/domUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";

/**
 * Returns the settings for the word count plugin
 *
 * @returns { object } - the word count settings
 */
export function getWordCountSettings() {
    return {
        wordCount: {
            // we handle character so we can count html as well to more accurately count
            displayCharacters: false,
        },
    };
}

/**
 * We add in our own character count element to the word count UI,
 * as we also count html characters
 *
 * @returns { HTMLDivElement } - the limit warning element
 */
function getCharacterElement() {
    const characterElement = document.createElement("div");
    characterElement.classList.add("ck-word-count__characters");
    characterElement.style.display = "none";
    return characterElement;
}

/**
 * The warning when the character limit has been reached
 *
 * @param { EditorConfig } config - the CKEditor config object
 * @returns { HTMLDivElement } - the limit warning element
 */
function getLimitWarningElement(config: EditorConfig) {
    const limitWarningElement = document.createElement("div");
    limitWarningElement.setAttribute("data-charLimitWarning", "");
    limitWarningElement.classList.add("ck-word-count__limit-warning");
    limitWarningElement.innerHTML = `<b>Warning:</b> The limit of ${config.maxCharCount} Characters has been reached. Please reduce the content to below the limit to ensure save.`;
    limitWarningElement.style.display = "none";
    return limitWarningElement;
}

/**
 * To display the warning when the character limit has been reached
 *
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor has replaced
 * @param { Editor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 * @param { HTMLDivElement } wordCountContainer - the word count container
 */
function updateWordCount(
    sourceElement: HTMLElement,
    editor: Editor,
    config: EditorConfig,
    wordCountContainer: HTMLDivElement
) {
    // if the max character count is -1 then we don't want to show the character count as the limit is disabled
    if (config.maxCharCount === -1) {
        return;
    }

    const onUpdateText = function () {
        const currentCharacterLength = editor.getData().length;
        characterCountElement.innerText = `Characters: ${currentCharacterLength}`;
        if (config.maxCharCount !== undefined && currentCharacterLength > config.maxCharCount) {
            characterCountElement.style.display = "block";
            limitWarningElement.style.display = "block";
            if (!config.showWordCount) {
                wordCountContainer.style.display = "flex";
            }
            sourceElement.dispatchEvent(new CustomEvent(EditorEvent.EDITOR__MAX_CHAR_COUNT_EXCEEDED));
            wordCountContainer.setAttribute("data-maxCharCountExceededAtLastCheck", "1");
        } else {
            characterCountElement.style.display = "none";
            limitWarningElement.style.display = "none";
            if (!config.showWordCount) {
                wordCountContainer.style.display = "none";
            }
            if (wordCountContainer.getAttribute("data-maxCharCountExceededAtLastCheck") === "1") {
                sourceElement.dispatchEvent(new CustomEvent(EditorEvent.EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED));
            }
            wordCountContainer.setAttribute("data-maxCharCountExceededAtLastCheck", "0");
        }
    };

    const characterCountElement = wordCountContainer.querySelector(".ck-word-count__characters") as HTMLDivElement;
    const limitWarningElement = wordCountContainer.querySelector(".ck-word-count__limit-warning") as HTMLDivElement;
    editor.plugins.get("WordCount").on("update", onUpdateText);

    onUpdateText(); // Run once initially in case max char count already exceeded due to content loaded from database.
}

/**
 * Add the word count UI to the bottom of the editor
 * Its assumed this method is called once the editor has been created,
 * so editorTextArea.nextSibling will be the newly created editor instance
 *
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor has replaced
 * @param { Editor } editor - the CKEditor instance
 * @param { EditorConfig } config - the CKEditor config object
 */
export function insertWordCount(sourceElement: HTMLElement, editor: Editor, config: EditorConfig) {
    const wordCountContainer = editor.plugins.get("WordCount").wordCountContainer as HTMLDivElement;
    insertElementAfter(sourceElement.nextSibling, wordCountContainer);
    insertElementAfter(wordCountContainer.lastChild, getCharacterElement());
    insertElementAfter(wordCountContainer.lastChild, getLimitWarningElement(config));
    if (config.showWordCount) {
        wordCountContainer.style.display = "flex";
    } else {
        wordCountContainer.style.display = "none";
    }
    updateWordCount(sourceElement, editor, config, wordCountContainer);
}
