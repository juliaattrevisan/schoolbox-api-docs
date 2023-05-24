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

    let containingForm: HTMLFormElement | null;
    let submitButtons: HTMLElement[];

    if (config.maxCharCountSubmitButtons && config.maxCharCountSubmitButtons.length > 0) {
        containingForm = null;
        submitButtons = config.maxCharCountSubmitButtons;
    } else {
        containingForm = sourceElement.closest("form");
        submitButtons =
            containingForm === null
                ? []
                : Array.from(containingForm.querySelectorAll("input[type=submit], button[type=submit]"));
    }

    if (submitButtons.length > 0) {
        addListenersForDisablingSubmitButtons(sourceElement, containingForm, Array.from(submitButtons));
    }

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
 * When the max char count has been exceeded on the ckeditorSourceEl, disable the form's submit button(s).
 * When the max char count is no longer exceeded on all ckeditor source elements in the form, re-enable the submit button(s).
 *
 * Some testing URLS (sales data):
 *  * https://vagrant.dev.schoolbox.cloud/adminv2/summative-report/create (single submit <input>)
 *  * https://vagrant.dev.schoolbox.cloud/news/34/update (single submit <button>)
 *  * https://vagrant.dev.schoolbox.cloud/news/87/update (multiple submit <button>s)
 *  * https://vagrant.dev.schoolbox.cloud/curriculum (config.maxCharCountSubmitButtons used)
 *
 *  This function is not intended to handle every possible configuration of CKEditor fields inside forms.  If this
 *  function does not disable/enable the submit form button in a specific case, it may be best to handle that case
 *  separately.  See for example the special case handling of the EDITOR__MAX_CHAR_COUNT_EXCEEDED and
 *  EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED events in vue/components/ckeditor/add-comment-input.vue./**
 *
 * @param { HTMLElement } ckeditorSourceEl
 * @param { HTMLFormElement } containingForm
 * @param { Array<HTMLElement> } submitButtons
 */
function addListenersForDisablingSubmitButtons(
    ckeditorSourceEl: HTMLElement,
    containingForm: HTMLFormElement | null,
    submitButtons: Array<HTMLElement>
) {
    for (let button of submitButtons) {
        // Now we know we have a button, assign it to a const.
        const submitButton: Element = button;
        const buttonTextOriginal: string = getButtonText(submitButton);

        ckeditorSourceEl.addEventListener("EDITOR__MAX_CHAR_COUNT_EXCEEDED", function () {
            changeButtonState(
                submitButton,
                true,
                buttonTextOriginal + " (disabled because content storage limit exceeded)"
            );
        });

        ckeditorSourceEl.addEventListener("EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED", function () {
            if (containingForm !== null) {
                // Only enable save button if no max. character count has been exceeded on any ckeditor field in the form.
                if (containingForm.querySelectorAll('[data-maxCharCountExceededAtLastCheck="1"]').length === 0) {
                    changeButtonState(submitButton, false, buttonTextOriginal);
                }
            } else {
                changeButtonState(submitButton, false, buttonTextOriginal);
            }
        });
    }
}

/**
 * Return the button text for buttons that are <input> or <button>.
 *
 * @param { Element } buttonElem
 */
function getButtonText(buttonElem: Element) {
    const buttonTagName = buttonElem.tagName.toLowerCase();
    let buttonText = null;

    switch (buttonTagName) {
        case "input":
            buttonText = buttonElem.getAttribute("value");
            break;
        case "button":
            buttonText = buttonElem.innerHTML;
            break;
        default:
            throw "Unsupported button tag name '" + buttonTagName + "'.";
    }

    return buttonText ?? "";
}

/**
 * Change button text for buttons that are <input> or <button>.
 *
 * @param { HTMLElement } buttonElem
 * @param { Boolean } maxCharCountExceeded
 * @param { String } newText
 */
function changeButtonState(buttonElem: HTMLElement, maxCharCountExceeded: Boolean, newText: string) {
    const buttonTagName = buttonElem.tagName.toLowerCase();

    switch (buttonTagName) {
        case "input":
            buttonElem.setAttribute("value", newText);
            break;
        case "button":
            buttonElem.innerHTML = newText;
            break;
        default:
            throw "Unsupported button tag name '" + buttonTagName + "'.";
    }

    buttonElem.disabled = maxCharCountExceeded;

    if (maxCharCountExceeded) {
        buttonElem.setAttribute("data-ckEditorMaxCharCountExceededAtLastCheck", "");
    } else {
        buttonElem.removeAttribute("data-ckEditorMaxCharCountExceededAtLastCheck");
    }
}
