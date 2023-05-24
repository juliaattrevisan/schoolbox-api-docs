/**
 * Loads and display the ckeditor, and once loaded prefill the initialValue if it's available
 * This setup uses https://vuejs.org/guide/reusability/composables.html, a Vue 3 alternative to mixins
 *
 * We currently use this for the description in add curriculum, add subsection and update an existing node
 * It's linked specifically to description as we are removing embed media buttons which is currently specific just to these description fields
 */

import { isScriptAdded, injectScript } from "@js/util/domUtils";

/**
 * Initialise the composable
 *
 * @param { string } $initialValue - the initial string value to input into the ckeditor
 * @param { string } ckeditorSelector - the selector to target the CKEditor
 * @param { Ref<HTMLButtonElement> } submitButton
 * @returns { string } - the initial value
 */
export function useDescriptionEditor(
    $initialValue: string,
    ckeditorSelector: string,
    submitButton: Ref<HTMLButtonElement | null>
) {
    const initialValue = window.Vue.ref($initialValue);

    /**
     * When a new editor is created
     */
    function onEditorCreated(event) {
        const editor = event.detail.editor;

        if (initialValue.value !== null) {
            editor.setData(initialValue.value); // set the initial data
        }

        editor.model.document.on("change:data", () => {
            initialValue.value = editor.getData() as string;
        });
    }

    /**
     * load the ckeditor into the browser
     */
    async function loadCKEditor() {
        try {
            const ckeditorScript = `${cdnPrefix}/js/bundle/editor.js?v=${siteVersion}`; // cdnPrefix and siteVersion are global vars from chrome.twig
            if (!isScriptAdded(ckeditorScript)) {
                await injectScript(ckeditorScript);
            }

            if (submitButton.value === null) {
                console.error("Could not find submit button for CKEditor '" + ckeditorSelector + "' field.");
            }

            const ckeditorElem = document.getElementById(ckeditorSelector) as HTMLElement;

            ckeditorElem.addEventListener("EDITOR__CREATED", onEditorCreated);

            document.dispatchEvent(
                new CustomEvent("EDITOR__CREATE", {
                    detail: {
                        targetElements: [`#${ckeditorSelector}`],
                        toolbar: "simple",
                        maxCharCountSubmitButtons: [submitButton.value],
                    },
                })
            );
        } catch (error) {
            throw new Error(error);
        }
    }

    /**
     * to load the ckeditor when ready
     */
    window.Vue.onMounted(() => {
        loadCKEditor();
    });

    return initialValue;
}
