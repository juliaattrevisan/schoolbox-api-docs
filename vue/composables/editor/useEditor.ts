/**
 * Loads and display the ckeditor, and once loaded prefill the currentValue if it's available
 * This setup uses https://vuejs.org/guide/reusability/composables.html, a Vue 3 alternative to mixins
 */

import { EditorEvent } from "@js/editor/event/editorEvent";
import { isScriptAdded, injectScript } from "@js/util/domUtils";
import { EditorConfig } from "@js/editor/types/editorTypes";

/**
 * Initialise the composable
 * @param { string } $currentValue - the initial string value to input into the ckeditor
 * @param { string } ckeditorSelector - the selector to target the CKEditor
 * @param { EditorConfig } editorConfig - the config to use when creating the editor
 * @returns { string } - the current value of the editor input
 */
export function useEditor($currentValue: string, ckeditorSelector: string, editorConfig: EditorConfig) {
    const currentValue = window.Vue.ref($currentValue);

    /**
     * When a new editor is created
     * @param { object } event - Javascript event object
     */
    function onEditorCreated(event: object) {
        const editor = event.detail.editor as object;
        if (currentValue.value !== null) {
            editor.setData(currentValue.value); // set the initial data
        }
        editor.model.document.on("change:data", () => {
            currentValue.value = editor.getData() as string;
        });
    }

    /**
     * load the ckeditor into the browser
     * Note that this doesn't currently handle the instantiation of multiple editors simultaneously unless the ckeditor script is already loaded
     */
    async function loadCKEditor() {
        try {
            const ckeditorScript = `${window.cdnPrefix}/js/bundle/editor.js?v=${window.siteVersion}`; // cdnPrefix and siteVersion are global vars from chrome.twig
            if (!isScriptAdded(ckeditorScript)) {
                await injectScript(ckeditorScript);
            }
            const ckeditorElement = document.getElementById(ckeditorSelector) as HTMLElement;

            ckeditorElement.addEventListener(EditorEvent.EDITOR__CREATED, onEditorCreated);

            document.dispatchEvent(
                new CustomEvent(EditorEvent.EDITOR__CREATE, {
                    detail: editorConfig,
                    // detail: {
                    //     targetElements: [`#${ckeditorSelector}`],
                    //     hasTokens: true,
                    // },
                })
            );
        } catch (error) {
            throw new Error(error);
        }
    }

    window.Vue.onMounted(async () => {
        await loadCKEditor();
    });

    return currentValue;
}
