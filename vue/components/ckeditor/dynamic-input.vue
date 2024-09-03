<script>
/**
 * Lazy load the CKEditor instance on demand
 * NOTE:
 * This component was ditched mid-way through and is not used anywhere.
 * As a result its functionality cannot be guaranteed.
 * However as we may want to use it or something similar in the future, it's being kept for now.
 */

let __uniqueId = 0; //to ensure multiple instances of CKEditor on the same page have unique ids
let __isFirstCKEditorLoad = true; //so a page only loads the CKEditor scripts once

import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";
import { injectScript } from "@js/util/domUtils";
import configTypes from "@js/editor/editorConfigTypes";

export default {
    mixins: [cdnPrefixMixin],

    props: {
        /* The id of the input/textarea element, supplied
         * by whatever summons this component
         *
         * (Historically, used when the comment boxes are in completely different js bundles,
         * as __uniqueId wouldn't increment in this case)
         *
         * Note that when you try to extract a ckeditor's contents from outside
         * of this dynemic-input component you need to reference that ckeditor
         * by its id in window.sbProps.editors[id], directly - so if you programmatically
         * generate them it's a bit annoying.
         */
        presetUniqueId: {
            //
            type: String,
            required: false,
            default: "",
        },
        /* The name attribute for the input field, supplied
         * by whatever summons this component
         */
        inputName: {
            type: String,
            required: true,
        },
        /* A word that represents a singular form
         * for the thing you're adding e.g. "Comment"
         */
        singular: {
            type: String,
            required: true,
        },
        /* This is an identifier for an element which should be made hidden
         * when the ckeditor spawns. It shows a readonly version of what
         * would otherwise be in the ckeditor
         */
        proxy: {
            type: String,
            required: false,
        },
        /* The existing textarea content to prefill
         */
        value: {
            type: String,
            required: false,
        },
        /* Decide whether to show edit button
         * Users click this to activate a ckeditor
         */
        allowEdit: {
            type: Boolean,
            required: true,
        },
        /* Decide whether to show submit button
         * If you're using this as part of a bigger form, set to false
         * since you'll probably have a submit button elsewhere and you
         * don't need/want this special one
         */
        allowSubmit: {
            type: Boolean,
            required: true,
        },
        /* Spawn ckeditor immediately upon load so users don't need to
         * interact with the ui to start it
         */
        spawnImmediately: {
            type: Boolean,
            required: true,
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

    data() {
        return {
            isCKEditorLoading: false,
            isCKEditorLoaded: false,
            showEdit: true,
        };
    },

    created() {
        if (this.presetUniqueId === "") {
            this.uniqueId = __uniqueId.toString();
            __uniqueId += 1;
        } else {
            this.uniqueId = this.presetUniqueId;
        }
    },

    mounted() {
        /* Spawn immediately, if configured to do so
         */
        if (this.spawnImmediately) {
            this.spawnEditor();
        }
    },

    unmounted() {
        /**
         * check if a proxy existed, and if so display it again as per the saved display property
         */
        let proxy = document.querySelector("#" + this.proxy);
        if (proxy) {
            proxy.style.display = proxy.dataset.display;
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
                return "Loading";
            }

            return "Add " + this.singular;
        },

        /**
         * startupFocus: true on the CKEditor was not working when hiding by more practical display none means...
         *
         * @returns { Object }
         */
        getStyles() {
            return this.isCKEditorLoaded ? { position: "static" } : { position: "absolute", left: "-9999px" };
        },

        /**
         * Ensure there's something (anything) in the comment and that the form is not already processing
         *
         * @returns {Boolean}
         */
        isPostDisabled() {
            return false;
            // return this.newValue.trim() === '' || this.isProcessing;
        },
    },

    methods: {
        /**
         * To initialise the CKEditor, passing in the preset config options that exist globally on the $ jQuery object
         */
        spawnEditor() {
            this.init(configTypes.BASIC);

            /* If a proxy element exists, hide it while the dynamic input is visible
             */
            let proxy = document.querySelector("#" + this.proxy);

            if (proxy) {
                /**
                 * we store the original display property so we can apply it again when this component is unmounted
                 */
                proxy.dataset.display = proxy.style.display;
                proxy.style.display = "none";
            }

            /* Once the ck spawns you can also hide the edit button
             */
            this.showEdit = false;
        },

        /**
         * Loads ckeditor only when required rather than on page load
         *
         * @param { String } editorType an option within editorTypes.js to determine which config options to pass to CKEditor
         */
        async init() {
            try {
                this.isCKEditorLoading = true; //to replace 'Add a Comment' with 'Loading' once the prompt button is selected
                if (__isFirstCKEditorLoad) {
                    await injectScript(`${this.cdnPrefix}/js/bundle/editor.js?v=${this.siteVersion}`);
                    __isFirstCKEditorLoad = false;
                }
                this.$refs.comment.addEventListener(EditorEvent.EDITOR__CREATED, this.onEditorCreated);
                document.dispatchEvent(
                    new CustomEvent(EditorEvent.EDITOR__CREATE, {
                        detail: {
                            targetElements: [`#${this.presetUniqueId}`],
                            toolbar: "comment",
                        },
                    }),
                );
            } catch (error) {
                console.warn(error);
                this.isCKEditorLoading = false;
                this.$emit("on-load-error");
            }
        },

        /**
         * Once the CKEditor has been created, we track any data input changes to keep
         * the comment in sync and assign focus so the user can start typing
         *
         * @param { CustomEvent } event - the event object, containing the editor instance
         */
        onEditorCreated(event) {
            this.editor = event.detail.editor;
            this.editor.model.document.on("change:data", () => {
                this.newComment = this.editor.getData();
                this.$emit("value-changed", window.sbPropbs.editors[this.presetUniqueId].getData()); // notifying of any changes for a parent to maintain state
            });
            this.editor.editing.view.focus();
            this.isCKEditorLoading = false;
            this.isCKEditorLoaded = true;
        },

        /**
         * Notify the parent to submit the form
         */
        postItem() {
            const value = window.sbPropbs.editors[this.presetUniqueId].getData();
            this.$emit("post", value);
        },
    },
};
</script>

<template>
    <div class="dynamic-input">
        <div :style="getStyles">
            <textarea :id="this.presetUniqueId" :name="this.inputName" rows="4">{{ this.value }}</textarea>
            <button v-if="this.allowSubmit" class="button submit" @click="postItem" :disabled="isPostDisabled">
                Save
            </button>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.dynamic-input {
    margin-bottom: 1rem;
}
</style>
