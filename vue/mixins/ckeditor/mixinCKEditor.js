/**
 * To load CKEditor on request and ensure consistent display of CKEditor options etc
 */


let __uniqueId = 0; //to ensure multiple instances of CKEditor on the same page have unique ids
let __isFirstCKEditorLoad = true; //so a page only loads the CKEditor scripts once
 
import { injectScript } from '@js/util/domUtils';
import { EDITOR_DEFAULT_CONFIG, EDITOR_CONFIG_FOR_COMMENTS, EDITOR_CONFIG_WITH_USER_TOKENS } from '@js/editor/editorConfigTypes';

export default {
 
    data () {
        return {
            cKEditor_isCKEditorLoading: false,
            cKEditor_isCKEditorLoaded: false,
        }
    },

    beforeCreate () {
        this.cKEditor_uniqueId = __uniqueId.toString();
        __uniqueId += 1;
    },

    computed: {

        /**
         * The text within the comment prompt button, will change to loading when selected to notify
         *
         * @returns { String }
         */
        cKEditor_getAddCommentButtonText () {
            if (this.cKEditor_isCKEditorLoading) return 'Loading';
            else return 'Add a comment'
        },

        /**
         * startupFocus: true on the CKEditor was not working when hiding by more practical display none means...
         *
         * @returns { Object }
         */
        cKEditor_getStyles () {
            return this.cKEditor_isCKEditorLoaded ? { 'position': 'static' } : { 'position': 'absolute', 'left': '-9999px' };
        },

        /**
         * Ensure there's something (anything) in the comment and that the form is not already processing
         *
         * @returns {Boolean}
         */
        cKEditor_isPostCommentDisabled () {
            if (this.newComment.trim() === '' || this.isProcessing) {
                return true;
            } else {
                return false;
            }
        }

    },
 
    methods: {

        /**
         * Loads ckeditor only when required rather than on page load
         * 
         * @param { String } editorTypes an option within editorTypes.js to determine which config options to pass to CKEditor
         */
        async cKEditor_init (editorType) {
            try {
                this.cKEditor_isCKEditorLoading = true; //to replace 'Add a Comment' with 'Loading' once the prompt button is selected
                if (__isFirstCKEditorLoad) {
                    await injectScript(`${this.cdnPrefix}/js/ckeditor/ckeditor.js?v=${this.siteVersion}`);
                    await injectScript(`${this.cdnPrefix}/js/editor/jquery.editor.js?v=${this.siteVersion}`);
                    __isFirstCKEditorLoad = false;
                }
                $.fn.alaressEditor(this.getConfigOptions(editorType));
            } catch (error) {
                this.cKEditor_isCKEditorLoading = false; 
                this.$refs.toast.timeout('Loading comment box failed', false, 5000);
            }
        },

        /**
         * Return the appropriate config to display consistency depending on the editorType
         * these '$.alaress' methods are created and attached to the jQuery $ object in jquery.editor.js
         * 
         * @param { String } editorTypes an option within editorTypes.js to determine which config options to pass to CKEditor
         * 
         * @returns { Object }
         */
        getConfigOptions (editorType) {
            const configOptions = { //use computed property names to retrieve the appropriate config options: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Object_initializer#computed_property_names
                [EDITOR_DEFAULT_CONFIG]: $.alaressEditorConfig(this.getVueSpecificConfig()),
                [EDITOR_CONFIG_FOR_COMMENTS]: $.alaressEditorConfigForComments(this.getVueSpecificConfig()),
                [EDITOR_CONFIG_WITH_USER_TOKENS]: $.alaressEditorConfigWithUserTokens(this.getVueSpecificConfig()) 
            }
            return configOptions[editorType] || $.alaressEditorConfig(this.getVueSpecificConfig());
        },

        /**
         * The config that needs to be passed to CKEditor in order to for it to work in Vue land
         * 
         * @returns { Object }
         */
        getVueSpecificConfig () {
            return {
                targetElements: $(`#comment-${this.cKEditor_uniqueId}`),
                on: {
                    change: () => { // Bind the content of CKEditor into the comment model on change
                        this.newComment = CKEDITOR.instances[`comment-${this.cKEditor_uniqueId}`].getData(); 
                    },
                    instanceReady: () => { //can display the loaded CKEditor now
                        this.cKEditor_isCKEditorLoading = false;
                        this.cKEditor_isCKEditorLoaded = true;
                    }
                }
            }
        }

    }
 
}
 