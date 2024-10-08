import type { WordCountConfig } from "@ckeditor/ckeditor5-word-count";
import { insertElementAfter } from "@js/util/domUtils";
import type { EditorDeletedEventInfo } from "@js/editor/event/editorEvent";
import { EditorEvent } from "@js/editor/event/editorEvent";
import { getSourceElementIdentifier } from "@js/editor/config/editorCheckData";
import type { SbxEditor, SbxEditorConfig, SbxEditorElement } from "@js/editor/types/editorTypes";

/**
 * Returns the settings for the word count plugin
 * @returns - the word count settings
 */
export function getWordCountSettings(): { wordCount: WordCountConfig } {
    return {
        wordCount: {
            // we handle character, so we can count html as well to more accurately count
            displayCharacters: false,
        },
    };
}

/**
 * @returns - Suffix starting with a space character.
 */
export function getButtonTextDisabledDueToStorageLimitSuffix(): string {
    return " (disabled because content storage limit exceeded)";
}

/**
 * We add in our own character count element to the word count UI,
 * as we also count html characters
 * @returns - the limit warning element
 */
function getCharacterElement(): HTMLDivElement {
    const characterElement = document.createElement("div");
    characterElement.classList.add("ck-word-count__characters");
    characterElement.style.display = "none";
    return characterElement;
}

/**
 * The warning when the character limit has been reached
 * @returns - the limit warning element
 */
function getLimitWarningElement(): HTMLDivElement {
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
 * Return the button text for buttons that are <input> or <button>.
 * @param buttonElem - The button whose text we want returned.
 * @returns - The button text or "" if no text could be read from the button.
 */
function getButtonText(buttonElem: Element): string {
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
            throw new Error(`Unsupported button tag name '${buttonTagName}'.`);
    }

    return buttonText ?? "";
}

/**
 * Change button text for buttons that are <input> or <button>.
 * @param buttonElem - The button whose state is to change.
 * @param maxCharCountExceeded - Whether the max char count on the relevant CKEditor textarea has been exceeded.
 * @param newText - The new text for the button.
 */
function changeButtonState(buttonElem: HTMLElement, maxCharCountExceeded: boolean, newText: string): void {
    const buttonTagName = buttonElem.tagName.toLowerCase();

    switch (buttonTagName) {
        case "input":
            buttonElem.setAttribute("value", newText);
            break;
        case "button":
            buttonElem.innerHTML = newText;
            break;
        default:
            throw new Error(`Unsupported button tag name '${buttonTagName}'.`);
    }

    (buttonElem as HTMLInputElement | HTMLButtonElement).disabled = maxCharCountExceeded;

    if (maxCharCountExceeded) {
        buttonElem.setAttribute("data-ckEditorMaxCharCountExceededAtLastCheck", "");
    } else {
        buttonElem.removeAttribute("data-ckEditorMaxCharCountExceededAtLastCheck");
    }
}

/**
 * Add event listeners for the EDITOR__MAX_CHAR_COUNT_EXCEEDED and EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED events.
 * When the max char count has been exceeded on the ckeditorSourceEl, disable the form's submit button(s).
 * When the max char count is no longer exceeded on all ckeditor source elements in the form, re-enable the submit button(s).
 *
 * Some testing URLS (sales data):
 *  - https://vagrant.dev.schoolbox.cloud/adminv2/summative-report/create (single submit <input>)
 *  - https://vagrant.dev.schoolbox.cloud/news/34/update (single submit <button>)
 *  - https://vagrant.dev.schoolbox.cloud/news/87/update (multiple submit <button>s)
 *  - https://vagrant.dev.schoolbox.cloud/curriculum (
 *       Vue example using config.addMaxCharCountDefaultEventListeners: false
 *       (The default event listeners directly manipulate the DOM, and in Vue land that should be avoided.
 *        Specifically, directly manipulating the DOM can cause reactivity to break in tracked properties.
 *        https://www.danvega.dev/blog/2019/04/18/tips-for-vue-developers-avoid-directly-manipulating-the-dom/)
 *    )
 *
 *  This function is not intended to handle every possible configuration of CKEditor fields inside forms.  If this
 *  function does not disable/enable the submit form button in a specific case, it may be best to handle that case
 *  separately.  See for example the special case handling of the EDITOR__MAX_CHAR_COUNT_EXCEEDED and
 *  EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED events in vue/components/ckeditor/add-comment-input.vue./**
 * @param ckeditorSourceEl - The textarea element to which the CKEditor has been attached.
 * @param containingForm - The form the submit buttons submit.
 * @param submitButtons - The submit buttons that submit the containingForm.
 */
function addListenersForDisablingSubmitButtons(
    ckeditorSourceEl: SbxEditorElement,
    containingForm: HTMLFormElement | null,
    submitButtons: Array<HTMLElement>,
): void {
    submitButtons.forEach((button) => {
        // Now we know we have a button, assign it to a const so it doesn't get redefined in the event listeners after loops
        const submitButton = button;

        // Get the original text of the submit button.  Note that if a new textarea is being added to a form that
        // already contains an overfilled textarea, the submit button will already have the buttonDisabledSuffix.  We
        // replace that suffix with blank to get the original text.
        const buttonDisabledSuffix = getButtonTextDisabledDueToStorageLimitSuffix();
        const buttonTextOriginal: string = getButtonText(submitButton).replace(buttonDisabledSuffix, "");

        ckeditorSourceEl.addEventListener(EditorEvent.EDITOR__MAX_CHAR_COUNT_EXCEEDED, () => {
            changeButtonState(submitButton, true, buttonTextOriginal + buttonDisabledSuffix);
        });

        ckeditorSourceEl.addEventListener(EditorEvent.EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED, () => {
            if (containingForm !== null) {
                // Only enable save button if no max. character count has been exceeded on any ckeditor field in the form.
                if (containingForm.querySelectorAll('[data-maxCharCountExceededAtLastCheck="1"]').length === 0) {
                    changeButtonState(submitButton, false, buttonTextOriginal);
                }
            } else {
                changeButtonState(submitButton, false, buttonTextOriginal);
            }
        });
    });
}

/**
 * To display the warning when the character limit has been reached.
 * @param sourceElement - the sourceElement the CKEditor has replaced
 * @param editor - the CKEditor instance
 * @param config - the CKEditor config object
 * @param wordCountContainer - the word count container
 */
function setupOnUpdateTextEventHandler(
    sourceElement: SbxEditorElement,
    editor: SbxEditor,
    config: SbxEditorConfig,
    wordCountContainer: HTMLDivElement,
): void {
    if (config.maxCharCount === undefined || config.maxCharCount === -1) {
        throw new Error("No maxCharCount has been set, so this function should not have been called.");
    }

    const characterCountElement = wordCountContainer.querySelector(".ck-word-count__characters") as HTMLDivElement;
    const limitWarningElement = wordCountContainer.querySelector(".ck-word-count__limit-warning") as HTMLDivElement;
    const limitExceededElement = wordCountContainer.querySelector(".ck-word-count__limit-exceeded") as HTMLSpanElement;
    const textEncoder = new TextEncoder();

    /**
     * Each time the text is updated, check whether the maxCharCount is exceeded or is no longer exceeded.
     */
    function onUpdateText(): void {
        const currentByteLength = textEncoder.encode(editor.getData()).length;
        const currentProportionOfMaxLength = currentByteLength / config.maxCharCount;
        const currentPercentageOfMaxLength = (currentProportionOfMaxLength * 100).toFixed(2);

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

    editor.plugins.get("WordCount").on("update", onUpdateText);

    onUpdateText(); // Run once initially in case max char count already exceeded due to content loaded from database.
}

/**
 * Check for state transitions from disabled to enabled, and on those transitions, fire the WordCount plugin 'update' event.
 * The purpose of doing this is to fix the disabled/enabled state of the form's submit button(s) if external code has
 * enabled the submit button without checking whether any of the CKEditor textareas are overfilled.  If any are overfilled,
 * the submit button should be disabled.
 * @param { Array<MutationRecord> } mutationList - An array of MutationRecords provided by a MutationObserver.
 */
const submitButtonMutationObserver = new MutationObserver((mutationList: Array<MutationRecord>) => {
    mutationList.forEach((mutation) => {
        if (mutation.type !== "attributes" || mutation.attributeName !== "disabled") {
            return; // Skip mutations we are not interested in.
        }

        const button = mutation.target as HTMLInputElement | HTMLButtonElement;

        if (button.disabled) {
            return; // We are only interested in transitions from disabled to enabled.
        }

        const form = button.closest("form");

        if (form === null) {
            return; // No containing form so nothing to do.
        }

        form.querySelectorAll("textarea").forEach((textarea) => {
            const fieldIdentifier = getSourceElementIdentifier(textarea);
            if (fieldIdentifier === null) {
                return; // No identifier (id/name) attribute on this textarea, so skip it.
            }
            const editor = window.sbProps.editors[fieldIdentifier];
            if (editor === undefined) {
                return; // No editor on this textarea, so skip it.
            }

            // The submit button has changed from disabled to enabled, so fire the wordcount update event.
            // This will cause the onUpdateText() function to run, which will then re-disable the submit button
            // if any of the CKEditor textareas are overfilled.
            // This is done so that other code does not have to know about this file.  The other code might
            // enable the submit button without knowing that it ought to be disabled because of overfilled
            // CKeditor textareas, and that will be totally okay, because the mutation observer will notice,
            // this code will be invoked to fix the situation.
            editor.plugins.get("WordCount").fire("update");
        });
    });
});

/**
 * The submit buttons may be disabled due to one or more of the CKEditor fields being overfilled.
 * Each time a CKEditor is removed from a page, we need to check whether the submit buttons are disabled,
 * and if so, whether any overfilled CKEditor fields remain.  If not, we can re-enable the submit buttons.
 *    Note that since this function is only concerned with submit buttons, it only needs to be called for deleted editors
 * that were inside a form.
 * @param eventDetail - Information about the event.
 */
function onEditorDeleted(eventDetail: EditorDeletedEventInfo): void {
    // If one submit button is disabled, they will all be disabled. Therefore, we only need to check the first.
    const firstSubmitButton: HTMLButtonElement | HTMLInputElement = eventDetail.submitButtons[0];

    if (firstSubmitButton !== null && firstSubmitButton.disabled) {
        let oneOrMoreEditorsAreOverfilled = false;

        // Check each CKEditor in the same form as the deleted editor to see whether its content storage limit has been exceeded.
        Object.values(window.sbProps.editors).forEach((ed) => {
            if (ed === undefined) return;

            const edSourceElement = ed.sourceElement;
            if (edSourceElement === undefined) return;

            const edContainingForm = edSourceElement.closest("form");

            if (edContainingForm === null) {
                return; // This editor does not belong to a form, so we don't need to check it.
            }

            const edContainingFormId = edContainingForm.getAttribute("id");

            if (eventDetail.containingFormId !== edContainingFormId) {
                return; // This editor does not belong to the form specified in eventDetail.
            }

            const container = ed.plugins.get("WordCount").wordCountContainer as HTMLDivElement;
            if (container.getAttribute("data-maxCharCountExceededAtLastCheck") === "1") {
                oneOrMoreEditorsAreOverfilled = true;
            }
        });

        if (!oneOrMoreEditorsAreOverfilled) {
            // Re-enable submit button(s) and restore the original button text.
            eventDetail.submitButtons.forEach((submitButton) => {
                const buttonText = submitButton.getAttribute("value") ?? "";
                const buttonTextOrig = buttonText.replace(getButtonTextDisabledDueToStorageLimitSuffix(), "");
                changeButtonState(submitButton, false, buttonTextOrig);
            });
        }
    }
}

/**
 * Add the word count UI to the bottom of the editor
 * Its assumed this method is called once the editor has been created,
 * so editorTextArea.nextSibling will be the newly created editor instance
 * @param sourceElement - the sourceElement the CKEditor has replaced
 * @param editor - the CKEditor instance
 * @param config - the CKEditor config object
 */
export function insertWordCount(sourceElement: SbxEditorElement, editor: SbxEditor, config: SbxEditorConfig): void {
    const wordCountContainer = editor.plugins.get("WordCount").wordCountContainer as HTMLDivElement;

    insertElementAfter(sourceElement.nextSibling as HTMLElement, wordCountContainer);
    insertElementAfter(wordCountContainer.lastChild as HTMLElement, getCharacterElement());
    insertElementAfter(wordCountContainer.lastChild as HTMLElement, getLimitWarningElement());

    if (config.showWordCount) {
        wordCountContainer.style.display = "flex";
    } else {
        wordCountContainer.style.display = "none";
    }

    // We only need to set up the maxCharCount-related event handlers if config.maxCharCount is enabled.
    // Testing URL for page with word count enabled but maxCharCount disabled:
    //  * https://vagrant.dev.schoolbox.cloud/learning/assessments/733/25/submit
    if (config.maxCharCount !== -1) {
        setupOnUpdateTextEventHandler(sourceElement, editor, config, wordCountContainer);

        // Default event listeners should be added by default (obviously).
        if (config.addMaxCharCountDefaultEventListeners === undefined) {
            config.addMaxCharCountDefaultEventListeners = true;
        }

        if (config.addMaxCharCountDefaultEventListeners) {
            const containingForm = sourceElement.closest("form");
            const submitButtons: Array<HTMLButtonElement | HTMLInputElement> =
                containingForm === null
                    ? []
                    : Array.from(containingForm.querySelectorAll("input[type=submit], button[type=submit]"));

            if (submitButtons.length > 0) {
                addListenersForDisablingSubmitButtons(sourceElement, containingForm, Array.from(submitButtons));

                submitButtons.forEach((submitButton) => {
                    // NOTE: The below line may be called multiple times (if multiple editors are added to a page), but the
                    //       repeated calls will not cause the submit button to be observed multiple times.  This is because
                    //       the same submitButtonMutationObserver is used each time, and repeated calls to observe the same
                    //       HTMLElement will overwrite rather than add duplicates.
                    submitButtonMutationObserver.observe(submitButton, { attributes: true });
                });
            }
        }
    }

    // If the editor is contained within a form...
    if (sourceElement.closest("form") !== null) {
        // NOTE: The below line may be called multiple times (if multiple editors are added to a page), but the repeated
        //       calls will not cause the event listener to be added multiple times.  This would be different if the
        //       function was defined inside the call below.
        document.addEventListener(EditorEvent.EDITOR__DELETED, (e) => onEditorDeleted(e.detail));
    }
}
