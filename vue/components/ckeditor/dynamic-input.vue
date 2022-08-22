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
        /* A word that represents a singular form
         * for the thing you're adding e.g. "Comment"
         */
        singular: {
            type: String,
            required: true
        },
        /* The name attribute for the input field, supplied
         * by whatever summons this component
         */
        inputName: {
            type: String,
            required: true
        },
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
            newValue: '',
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
         * The text within the prompt button, will change to loading when selected to notify
         *
         * @returns { String }
         */
        getAddButtonText() {
            if (this.isCKEditorLoading) {
                return 'Loading';
            }

            return 'Add a ' + this.singular;
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
        isPostDisabled () {
            if (this.newValue.trim() === '' || this.isProcessing) {
                return true;
            }

            return false;
        }
    },
 
    methods: {

        /**
         * To initialise the CKEditor, passing in the preset config options that exist globally on the $ jQuery object
         */
        onAddSelected () {
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
                        this.newValue = CKEDITOR.instances[this.inputName].getData();
                    },
                    instanceReady: () => { //can display the loaded CKEditor now
                        this.isCKEditorLoading = false;
                        this.isCKEditorLoaded = true;
                    }
                },
                startupFocus: true,
                targetElements: $(`#dynamic-input-${this.uniqueId}`),
            }
        },

        /**
         * Notify the parent and clear the comment box
         */
        postItem () {
            const comment = this.newValue;

            /* TODO What's this
             */
            this.$emit('on-post-comment', comment);

            this.newValue = '';
            CKEDITOR.instances[this.inputName].setData(this.newValue); //clear out the CKEditor for another possible post
        },
    }
}

</script>

<template>
   <div class="add-commment-input">
       <button class="btn-show-ckeditor" v-show="!isCKEditorLoaded" @click.once="onAddSelected">{{ getAddButtonText }}</button>
        <div :style="getStyles">
            <textarea :id="`dynamic-input-${uniqueId}`" :name="this.inputName" rows="4"></textarea>
            <button class="button submit"
                @click="postItem"
                :disabled="isPostDisabled">Post {{ this.singular }}</button>
        </div>
   </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/core/base/_colors.scss';
@import 'www/static/css/core/base/_variables.scss';

.add-commment-input {
    margin-bottom: 1rem;
}

.btn-show-ckeditor {
    background: $white;
    border: 1px solid $form-border-color;
    color: $content-ui-foreground;
    cursor: text;
    font-size: 0.8rem;
    padding: 0.625rem 0.625rem 1.25rem 0.625rem;
    text-align: left;
    transition: all 0.1s ease-out;
    width: 100%;
    &:hover {
        border-color: $content-ui-foreground;
    }
}

</style>