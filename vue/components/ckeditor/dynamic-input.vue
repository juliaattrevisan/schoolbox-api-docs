<script>

/**
 * Lazy load the CKEditor instance on demand
 */

let __uniqueId = 0; //to ensure multiple instances of CKEditor on the same page have unique ids
let __isFirstCKEditorLoad = true; //so a page only loads the CKEditor scripts once

import cdnPrefixMixin from '@schoolboxVue/components/cdn-prefix-mixin'
import { injectScript } from '@js/util/domUtils';
import configTypes from '@js/editor/editorConfigTypes';

export default {

    mixins: [
        cdnPrefixMixin,
    ],

    props: {
        /* The id of the input/textarea element, supplied
         * by whatever summons this component
         *
         * (Historically, used when the comment boxes are in completely different js bundles,
         * as __uniqueId wouldn't increment in this case)
         *
         * Note that when you try to extract a ckeditor's contents from outside
         * of this dynemic-input component you need to reference that ckeditor
         * by its id in CKEDITOR.instances[id], directly - so if you programmatically
         * generate them it's a bit annoying.
         */
        presetUniqueId: { //
            type: String,
            required: false,
            default: '',
        },
        /* The name attribute for the input field, supplied
         * by whatever summons this component
         */
        inputName: {
            type: String,
            required: true
        },
        /* A word that represents a singular form
         * for the thing you're adding e.g. "Comment"
         */
        singular: {
            type: String,
            required: true
        },
        /* The existing textarea content to prefill
         */
        value: {
            type: String,
            required: false
        },
        /* Decide whether to show edit button
         * Users click this to activate a ckeditor
         */
        allowEdit: {
            type: Boolean,
            required: true
        },
        /* Decide whether to show submit button
         * If you're using this as part of a bigger form, set to false
         * since you'll probably have a submit button elsewhere and you
         * don't need/want this special one
         */
        allowSubmit: {
            type: Boolean,
            required: true
        },
        /* Spawn ckeditor immediately upon load so users don't need to
         * interact with the ui to start it
         */
        spawnImmediately: {
            type: Boolean,
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

    mounted () {
        /* Spawn immediately, if configured to do so
         */
        if (this.spawnImmediately) {
            this.spawnEditor();
        }
    },

    computed: {

        /**
         * The text within the prompt button, will change to loading when selected to notify
         *
         * @returns { String }
         */
        getEditButtonText() {
            if (this.isCKEditorLoading) {
                return 'Loading';
            }

            return 'Edit ' + this.singular;
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
            return false;
            // return this.newValue.trim() === '' || this.isProcessing;
        }
    },

    methods: {

        /**
         * To initialise the CKEditor, passing in the preset config options that exist globally on the $ jQuery object
         */
        spawnEditor () {
            this.init(configTypes.BASIC);
        },

        /**
         * Loads ckeditor only when required rather than on page load
         *
         * @param { String } editorType an option within editorTypes.js to determine which config options to pass to CKEditor
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
         * @param { String } editorType an option within editorTypes.js to determine which config options to pass to CKEditor
         *
         * @returns { Object }
         */
        getConfigOptions (editorType) {
            const configOptions = { //use computed property names to retrieve the appropriate config options: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Object_initializer#computed_property_names
                [configTypes.DEFAULT]: $.alaressEditorConfig(this.getVueSpecificConfig()),
                [configTypes.FOR_COMMENTS]: $.alaressEditorConfigForComments(this.getVueSpecificConfig()),
                [configTypes.WITH_USER_TOKENS]: $.alaressEditorConfigWithUserTokens(this.getVueSpecificConfig()),
                [configTypes.BASIC]: $.alaressEditorConfigBasic(this.getVueSpecificConfig()),
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
                targetElements: $(`#${this.presetUniqueId}`),
            }
        },

        /**
         * Notify the parent and clear the comment box
         */
        postItem () {
            const comment = this.newValue;

            /* TODO What's this
             * I presume this occurs when you hit a submit button and that people
             * used it to disable the submit button to prevent double submits
             */
            this.$emit('on-post-comment', comment);

            this.newValue = '';
            CKEDITOR.instances[this.inputName].setData(this.newValue); //clear out the CKEditor for another possible post
        },
    }
}

</script>

<template>
    <div>
        <button v-if="this.allowEdit" class="show-ckeditor-button" v-show="!isCKEditorLoaded" @click.once="spawnEditor">{{ getEditButtonText }}</button>
        <div :style="getStyles">
            <textarea :id="this.presetUniqueId" :name="this.inputName" rows="4">{{ this.value }}</textarea>
            <button v-if="this.allowSubmit" class="button submit" @click="postItem" :disabled="isPostDisabled">Save</button>
        </div>
    </div>
</template>