/**
 * Loads and display the ckeditor, and once loaded prefil the initialValue if it's available
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
 * @param { string } $ckeditorInstance - the selector to target the CKEditor
 * @returns { string } - the initial value
 */
export function useDescriptionEditor($initialValue: string, $ckeditorInstance: string) {
    const initialValue = window.Vue.ref($initialValue);
    const ckeditorInstance = $ckeditorInstance;

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

            let submitButton = document.querySelector("button.submit[data-submit-button-for=" + ckeditorInstance + "]");

            if (submitButton === null) {
                console.error("Could not find submit button for CKEditor '" + ckeditorInstance + "' field.");
            }

            document.getElementById(ckeditorInstance).addEventListener("EDITOR__CREATED", onEditorCreated);
            document.dispatchEvent(
                new CustomEvent("EDITOR__CREATE", {
                    detail: {
                        targetElements: [`#${ckeditorInstance}`],
                        toolbar: "simple",
                        maxCharCountSubmitButtons: [submitButton],
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
