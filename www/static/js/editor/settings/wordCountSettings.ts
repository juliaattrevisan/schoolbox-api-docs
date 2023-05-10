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
    const limitExceededElement = document.createElement("span");
    limitExceededElement.classList.add("ck-word-count__limit-exceeded");
    limitExceededElement.innerHTML =
        "<b>Warning:</b> The content storage limit has been exceeded.  Please remove some content before saving.";

    const limitWarningElement = document.createElement("div");
    limitWarningElement.classList.add("ck-word-count__limit-warning");
    limitWarningElement.append(limitExceededElement);
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
    const characterCountElement = wordCountContainer.querySelector(".ck-word-count__characters") as HTMLDivElement;
    const limitWarningElement = wordCountContainer.querySelector(".ck-word-count__limit-warning") as HTMLDivElement;
    const limitExceededElement = wordCountContainer.querySelector(".ck-word-count__limit-exceeded") as HTMLSpanElement;
    const textEncoder = new TextEncoder();
    const onUpdateText = function () {
        if (config.maxCharCount !== undefined && config.maxCharCount > 0) {
            const currentByteLength = textEncoder.encode(editor.getData()).length,
                currentProportionOfMaxLength = currentByteLength / config.maxCharCount,
                currentPercentageOfMaxLength = (currentProportionOfMaxLength * 100).toFixed(2);
            characterCountElement.innerText = `Size: ${currentPercentageOfMaxLength}% of limit`;

            // Thresholds:
            // if < 90% full, hide the limit notice
            // if 90-100% full, show the limit but hide the exceeded error
            // if > 100% full, show the limit and the exceeded error
            if (currentProportionOfMaxLength > 0.9) {
                characterCountElement.style.display = "block";
                limitWarningElement.style.display = "block";
                if (!config.showWordCount) {
                    wordCountContainer.style.display = "flex";
                }
            } else {
                characterCountElement.style.display = "none";
                limitWarningElement.style.display = "none";
                if (!config.showWordCount) {
                    wordCountContainer.style.display = "none";
                }
            }

            if (currentProportionOfMaxLength > 1) {
                limitExceededElement.style.display = "inline";
                sourceElement.dispatchEvent(new CustomEvent(EditorEvent.EDITOR__MAX_CHAR_COUNT_EXCEEDED));
                wordCountContainer.setAttribute("data-maxCharCountExceededAtLastCheck", "1");
            } else {
                limitExceededElement.style.display = "none";

                // Remember the previous value of data-maxCharCountExceededAtLastCheck before updating the value.
                // (We have just re-run the check, and found the max char count is no longer exceeded).
                const prevValue = wordCountContainer.getAttribute("data-maxCharCountExceededAtLastCheck");

                // Update the value of data-maxCharCountExceededAtLastCheck before firing the no-longer-exceeded event.
                wordCountContainer.setAttribute("data-maxCharCountExceededAtLastCheck", "0");

                if (prevValue === "1") {
                    sourceElement.dispatchEvent(new CustomEvent(EditorEvent.EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED));
                }
            }
        }
    };

    addListenersForDisablingSubmitButton(sourceElement);

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

/**
 * Add event listeners for the EDITOR__MAX_CHAR_COUNT_EXCEEDED and EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED events.
 * When the max char count has been exceeded on the ckeditorSourceEl, disable the form's submit button.
 * When the max char count is no longer exceeded on all ckeditor source elements in the form, re-enable the submit button.
 *
 * @param ckeditorSourceEl
 */
function addListenersForDisablingSubmitButton(ckeditorSourceEl: HTMLElement) {
    const containingForm = ckeditorSourceEl.closest("form");

    if (containingForm === null) {
        // No containing form, so do nothing.
        return;
    }

    const submitButton = containingForm.querySelector("input[type=submit]");

    if (submitButton === null) {
        // No submit button found, so do nothing.
        return;
    }
    ckeditorSourceEl.addEventListener("EDITOR__MAX_CHAR_COUNT_EXCEEDED", function () {
        submitButton.disabled = true;
        submitButton.setAttribute("value", "Save (disabled because content storage limit exceeded)");
    });

    ckeditorSourceEl.addEventListener("EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED", function () {
        // Only enable save button if no max. character count has been exceeded on any ckeditor field in the form.
        if (containingForm.querySelectorAll('[data-maxCharCountExceededAtLastCheck="1"]').length === 0) {
            submitButton.disabled = false;
            submitButton.setAttribute("value", "Save");
        }
    });
}
