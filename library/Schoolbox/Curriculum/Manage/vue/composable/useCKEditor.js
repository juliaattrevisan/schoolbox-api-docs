/**
 * Loads and display the ckeditor, and once loaded prefil the initialValue if it's available
 * This setup uses https://vuejs.org/guide/reusability/composables.html, a Vue 3 alternative to mixins
 *
 * We currently use this for the description in add curriculum, add subsection and update an existing node
 * It's linked to Curriculum for now as we are specifically removing embed media buttons which is currently specific just to these description fields
 */

import { ref, onMounted } from 'vue';
import { isScriptAdded, injectScript } from '@js/util/domUtils';


export function useCKEditor ($initialValue, $ckeditorInstance) {

    const initialValue = ref($initialValue);
    const ckeditorInstance = $ckeditorInstance;

    /**
     * to load the ckeditor when ready
     */
    onMounted(() => {
        loadCKEditor();
    });

    /**
     * load the ckeditor into the browser
     */
    async function loadCKEditor () {
        try {
            const ckeditorScript = `${cdnPrefix}/js/ckeditor/ckeditor.js?v=${siteVersion}`; //cdnPrefix and siteVersion are global vars from chrome.twig
            if (!isScriptAdded(ckeditorScript)) {
                await injectScript(ckeditorScript);
            }
            const jqueryEditorScript = `${cdnPrefix}/js/editor/jquery.editor.js?v=${siteVersion}`; //cdnPrefix and siteVersion are global vars from chrome.twig
            if (!isScriptAdded(jqueryEditorScript)) {
                await injectScript(jqueryEditorScript);
            }
            $.fn.alaressEditor($.alaressEditorConfigForComments(getVueSpecificConfig()));
        } catch (error) {
            console.warn(error);
        }
    }

    /**
     * The config that needs to be passed to CKEditor in order to for it to work in Vue land
     *
     * @returns { Object }
     */
    function getVueSpecificConfig () {
        return {
            on: {
                change: () => { // Bind the content of CKEditor into the description model on change
                    initialValue.value = CKEDITOR.instances[ckeditorInstance].getData();
                },
                instanceReady: (event) => { //can display the loaded CKEditor now
                    if (initialValue.value !== null) {
                        event.editor.setData(initialValue.value); //set the initial data
                        removeMediaEmbedButtons(event.editor.container.$);
                    }
                }
            },
            targetElements: $(`#${ckeditorInstance}`),
        }
    }

    /**
     * Remove these buttons as media shouldn't be added to the description
     *
     * @returns { HTMLElement }
     */
    function removeMediaEmbedButtons (ckeditor) {
        const videoAudioButton = ckeditor.querySelector('.cke_button__schoolboxresourcemedia');
        videoAudioButton.style.display = 'none';
        const imageButton = ckeditor.querySelector('.cke_button__schoolboxresourceimage');
        imageButton.style.display = 'none';
    }

    return initialValue;

}