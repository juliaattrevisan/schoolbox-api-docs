/**
 * Loads and display the ckeditor, and once loaded prefill the currentValue if it's available
 * This setup uses https://vuejs.org/guide/reusability/composables.html, a Vue 3 alternative to mixins
 */

import type { Ref } from "vue";
import { EditorEvent, type EditorCreatedEventInfo } from "@js/editor/event/editorEvent";
import { injectScript } from "@js/util/domUtils";
import type { PartialSbxEditorConfig } from "@js/editor/types/editorTypes";

/**
 * Initialise the composable
 * @param $currentValue - the initial string value to input into the ckeditor, or a ref to the value
 * @param ckeditorSelector - the selector to target the CKEditor, or the element itself
 * @param editorConfig - the config to use when creating the editor
 * @param isMounted - whether or not we are already mounted, and to skip the onMounted hook
 * @param callback - a callback to run after the editor is created including EditorCreatedEventInfo
 * @returns - the current value of the editor input as a ref
 */
export function useEditor(
    $currentValue: string | Ref<string>,
    ckeditorSelector: string | HTMLElement,
    editorConfig: PartialSbxEditorConfig,
    isMounted: boolean = false,
    callback?: (event: EditorCreatedEventInfo) => void,
): Ref<string> {
    const currentValue = typeof $currentValue === "string" ? window.Vue.ref($currentValue) : $currentValue;

    /**
     * When a new editor is created
     * @param event - the event that triggered the creation of the editor
     */
    function onEditorCreated(event: CustomEvent): void {
        const { editor } = event.detail as EditorCreatedEventInfo;

        if (currentValue.value !== null) {
            editor.setData(currentValue.value); // set the initial data
        }
        editor.model.document.on("change:data", () => {
            currentValue.value = editor.getData();
        });

        if (callback) {
            callback(event.detail as EditorCreatedEventInfo);
        }
    }

    /**
     * load the ckeditor into the browser
     * Note that this doesn't currently handle the instantiation of multiple editors simultaneously unless the ckeditor script is already loaded
     */
    async function loadCKEditor(): Promise<void> {
        try {
            const ckeditorScript = `${window.cdnPrefix}/js/bundle/editor.js?v=${window.siteVersion}`; // cdnPrefix and siteVersion are global vars from chrome.twig
            await injectScript(ckeditorScript);

            const ckeditorElement =
                typeof ckeditorSelector === "string"
                    ? (document.querySelector(ckeditorSelector) as HTMLElement)
                    : ckeditorSelector;

            // avoid creating multiple editors for the same selector
            // @todo - this currently prevents a watch() from working once it's been unmounted/remounted
            // if (window.sbProps.editors[ckeditorSelector] !== undefined) {
            //     const editor = window.sbProps.editors[ckeditorSelector] as ClassicEditor;
            //     ckeditorElement.replaceWith(editor.ui.view.element as HTMLElement);
            //     return;
            // }

            ckeditorElement.addEventListener(EditorEvent.EDITOR__CREATED, (event) => {
                onEditorCreated(event as CustomEvent);
            });

            document.dispatchEvent(
                new CustomEvent(EditorEvent.EDITOR__CREATE, {
                    detail: editorConfig,
                }),
            );
        } catch (error) {
            throw new Error((error as Error).message);
        }
    }

    if (isMounted === true) {
        loadCKEditor().catch((error) => {
            throw new Error((error as Error).message);
        });
    } else {
        window.Vue.onMounted(async () => {
            await loadCKEditor();
        });
    }

    return currentValue;
}
