/**
 * Loads and display the ckeditor, and once loaded prefill the initialValue if it's available
 * This setup uses https://vuejs.org/guide/reusability/composables.html, a Vue 3 alternative to mixins
 *
 * We currently use this for the description in add curriculum, add subsection and update an existing node
 * It's linked specifically to description as we are removing embed media buttons which is currently specific just to these description fields
 */

import type { Ref } from "vue";
import { EditorCreatedEventInfo, EditorEvent } from "@js/editor/event/editorEvent";
import { isScriptAdded, injectScript } from "@js/util/domUtils";

/**
 * Initialise the composable
 * @param { string } $initialValue - the initial string value to input into the ckeditor
 * @param { string } ckeditorSelector - the selector to target the CKEditor
 * @param { Ref<boolean> } isMaxCharCountExceeded - Vue reference to boolean
 *  - true if the CKEditor field content storage limit was found to have been exceeded at last check
 * @returns { Ref<string> } - the initial value
 */
export function useDescriptionEditor(
    $initialValue: string,
    ckeditorSelector: string,
    isMaxCharCountExceeded: Ref<boolean>,
): Ref<string> {
    const initialValue = window.Vue.ref($initialValue);

    /**
     * When a new editor is created
     * @param { CustomEvent } event - Javascript event object
     */
    function onEditorCreated(event: CustomEvent) {
        const { editor } = event.detail as EditorCreatedEventInfo;

        if (initialValue.value !== null) {
            editor.setData(initialValue.value); // set the initial data
        }

        editor.model.document.on("change:data", () => {
            initialValue.value = editor.getData();
        });

        const ckeditorElem = document.getElementById(ckeditorSelector) as HTMLElement;

        // Override default event listener from wordCountSettings.ts because that function manipulates DOM directly, which messes up Vue reactivity.
        ckeditorElem.addEventListener(EditorEvent.EDITOR__MAX_CHAR_COUNT_EXCEEDED, () => {
            isMaxCharCountExceeded.value = true;
        });

        // Override default event listener from wordCountSettings.ts because that function manipulates DOM directly, which messes up Vue reactivity.
        ckeditorElem.addEventListener(EditorEvent.EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED, () => {
            isMaxCharCountExceeded.value = false;
        });
    }

    /**
     * load the ckeditor into the browser
     */
    async function loadCKEditor() {
        try {
            const ckeditorScript = `${window.cdnPrefix}/js/bundle/editor.js?v=${window.siteVersion}`; // cdnPrefix and siteVersion are global vars from chrome.twig
            if (!isScriptAdded(ckeditorScript)) {
                await injectScript(ckeditorScript);
            }

            const ckeditorElem = document.getElementById(ckeditorSelector) as HTMLElement;

            ckeditorElem.addEventListener(EditorEvent.EDITOR__CREATED, (event) => {
                onEditorCreated(event as CustomEvent);
            });

            document.dispatchEvent(
                new CustomEvent(EditorEvent.EDITOR__CREATE, {
                    detail: {
                        targetElements: [`#${ckeditorSelector}`],
                        removeFromToolbar: ["mediaEmbed"],
                        addMaxCharCountDefaultEventListeners: false,
                    },
                }),
            );
        } catch (error) {
            throw new Error((error as Error).message);
        }
    }

    /**
     * to load the ckeditor when ready
     */
    window.Vue.onMounted(async () => {
        await loadCKEditor();
    });

    return initialValue;
}
