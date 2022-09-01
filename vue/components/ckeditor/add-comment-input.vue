<script>

/**
 * Lazy load the CKEditor instance on demand and pass through the current comment
 * to the parent.
 */

let __uniqueId = 0; //to ensure multiple instances of CKEditor on the same page have unique ids
let __isFirstCKEditorLoad = true; //so a page only loads the CKEditor scripts once

import cdnPrefixMixin from '@schoolboxVue/components/cdn-prefix-mixin'
import { injectScript } from '@js/util/domUtils';
import { EDITOR_DEFAULT_CONFIG, EDITOR_CONFIG_FOR_COMMENTS, EDITOR_CONFIG_WITH_USER_TOKENS } from '@js/editor/editorConfigTypes';


export default {

    mixins: [
        cdnPrefixMixin,
    ],

    props: {
        isProcessing: {
            type: Boolean,
            required: true,
        },
        siteVersion: {
            type: String,
            required: true,
        },
        presetUniqueId: { //currently for when the comment boxes are in completely different js bundles, as __uniqueId wouldn't increment in this case
            type: String,
            required: false,
            default: '',
        },
    },

   data () {
        return {
            isCKEditorLoading: false,
            isCKEditorLoaded: false,
            newComment: '',
        }
    },

    created () {
        if (this.presetUniqueId === '') {
            this.uniqueId = __uniqueId.toString();
            __uniqueId += 1;
        } else {
            this.uniqueId = this.presetUniqueId;
        }
    },

    computed: {

        /**
         * The text within the comment prompt button, will change to loading when selected to notify
         *
         * @returns { String }
         */
        getAddCommentButtonText () {
            if (this.isCKEditorLoading) return 'Loading';
            else return 'Add a comment'
        },

        /**
         * startupFocus: true on the CKEditor was not working when hiding by more practical display none means...
         *
         * @returns { Object }
         */
        getStyles () {
            return this.isCKEditorLoaded ? { 'position': 'static' } : { 'position': 'absolute', 'left': '-9999px' };
        },

        /**
         * Ensure there's something (anything) in the comment and that the form is not already processing
         *
         * @returns {Boolean}
         */
        isPostCommentDisabled () {
            if (this.newComment.trim() === '' || this.isProcessing) {
                return true;
            } else {
                return false;
            }
        }

    },

    methods: {

        /**
         * To initialise the CKEditor, passing in the preset config options that exist globally on the $ jQuery object
         */
        onAddCommentSelected () {
            this.init(EDITOR_CONFIG_FOR_COMMENTS);
        },

        /**
         * Loads ckeditor only when required rather than on page load
         *
         * @param { String } editorTypes an option within editorTypes.js to determine which config options to pass to CKEditor
         */
        async init (editorType) {
            try {
                this.isCKEditorLoading = true; //to replace 'Add a Comment' with 'Loading' once the prompt button is selected
                if (__isFirstCKEditorLoad) {
                    await injectScript(`${this.cdnPrefix}/js/ckeditor/ckeditor.js?v=${this.siteVersion}`);
                    await injectScript(`${this.cdnPrefix}/js/editor/jquery.editor.js?v=${this.siteVersion}`);
                    __isFirstCKEditorLoad = false;
                }
                $.fn.alaressEditor(this.getConfigOptions(editorType));
            } catch (error) {
                this.isCKEditorLoading = false;
                this.$emit('on-load-error');
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
                on: {
                    change: () => { // Bind the content of CKEditor into the comment model on change
                        this.newComment = CKEDITOR.instances[`comment-${this.uniqueId}`].getData();
                    },
                    instanceReady: () => { //can display the loaded CKEditor now
                        this.isCKEditorLoading = false;
                        this.isCKEditorLoaded = true;
                    }
                },
                startupFocus: true,
                targetElements: $(`#comment-${this.uniqueId}`),
            }
        },

        /**
         * Notify the parent and clear the comment box
         */
        postComment () {
            const comment = this.newComment;
            this.$emit('on-post-comment', comment);
            this.newComment = '';
            CKEDITOR.instances[`comment-${this.uniqueId}`].setData(this.newComment); //clear out the CKEditor for another possible post
        },

    }

}

</script>

<template>
   <div class="add-commment-input">
       <button class="show-ckeditor-button" v-show="!isCKEditorLoaded" @click.once="onAddCommentSelected">{{ getAddCommentButtonText }}</button>
        <div :style="getStyles">
            <textarea :id="`comment-${uniqueId}`" rows="4"></textarea>
            <button class="button submit"
                @click="postComment"
                :disabled="isPostCommentDisabled">Post comment</button>
        </div>
   </div>
</template>

<style lang="scss" scoped>

.add-commment-input {
    margin-bottom: 1rem;
}

</style>