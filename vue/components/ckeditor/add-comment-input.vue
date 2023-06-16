<script>
/**
 * Lazy load the CKEditor instance on demand and pass through the current comment
 * to the parent.
 */

let __uniqueId = 0; //to ensure multiple instances of CKEditor on the same page have unique ids

import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";
import { injectScript, isScriptAdded } from "@js/util/domUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";

export default {
    mixins: [cdnPrefixMixin],

    props: {
        isProcessing: {
            type: Boolean,
            required: true,
        },
        siteVersion: {
            type: String,
            required: true,
        },
        presetUniqueId: {
            //currently for when the comment boxes are in completely different js bundles, as __uniqueId wouldn't increment in this case
            type: String,
            required: false,
            default: "",
        },
    },

    data() {
        return {
            isOverStorageLimit: false,
            isCKEditorLoading: false,
            isCKEditorLoaded: false,
            newComment: "",
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

    computed: {
        /**
         * The text within the comment prompt button, will change to loading when selected to notify
         *
         * @returns { String }
         */
        getAddCommentButtonText() {
            if (this.isCKEditorLoading) return "Loading";
            else return "Add a comment";
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
        isPostCommentDisabled() {
            if (this.newComment.trim() === "" || this.isProcessing || this.isOverStorageLimit) {
                return true;
            } else {
                return false;
            }
        },
    },

    methods: {
        /**
         * To initialise the CKEditor, passing in the preset config options that exist globally on the $ jQuery object
         */
        async onAddCommentSelected() {
            await this.init();
        },

        /**
         * Loads ckeditor only when required rather than on page load
         */
        async init() {
            try {
                this.isCKEditorLoading = true; // to replace 'Add a Comment' with 'Loading' once the prompt button is selected

                const editorScript = `${this.cdnPrefix}/js/bundle/editor.js?v=${this.siteVersion}`;

                if (!isScriptAdded(editorScript)) {
                    await injectScript(editorScript);
                }

                this.$refs.comment.addEventListener(EditorEvent.EDITOR__CREATED, this.onEditorCreated);

                document.dispatchEvent(
                    new CustomEvent(EditorEvent.EDITOR__CREATE, {
                        detail: {
                            targetElements: [`#comment-${this.uniqueId}`],
                            toolbar: "comment",
                        },
                    })
                );
            } catch (error) {
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
            });

            const sourceElement = event.detail.sourceElement;
            const commentId = sourceElement.getAttribute("id");
            const submitButton = document.querySelector("button.submit[id=submit-for-" + commentId + "]");
            const submitButtonTextOrig = submitButton.innerHTML;

            // Arrow function syntax ensures 'this' can be used inside event handler.
            sourceElement.addEventListener(EditorEvent.EDITOR__MAX_CHAR_COUNT_EXCEEDED, () => {
                this.isOverStorageLimit = true;
                submitButton.innerHTML = submitButtonTextOrig + " (disabled because maximum character count exceeded)";
            });

            // Arrow function syntax ensures 'this' can be used inside event handler.
            sourceElement.addEventListener(EditorEvent.EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED, () => {
                this.isOverStorageLimit = false;
                submitButton.setAttribute("value", submitButtonTextOrig);
            });

            this.editor.editing.view.focus();
            this.isCKEditorLoading = false;
            this.isCKEditorLoaded = true;
        },

        /**
         * Notify the parent and clear the comment box
         */
        postComment() {
            const comment = this.newComment;
            this.$emit("on-post-comment", comment);
            this.newComment = "";
            this.editor.setData(this.newComment); // clear out the CKEditor for another possible post
        },
    },
};
</script>

<template>
    <div class="add-commment-input">
        <button class="show-ckeditor-button" v-show="!isCKEditorLoaded" @click.once="onAddCommentSelected">
            {{ getAddCommentButtonText }}
        </button>
        <div :style="getStyles">
            <textarea :id="`comment-${uniqueId}`" ref="comment"></textarea>
            <button
                :id="`submit-for-comment-${uniqueId}`"
                class="button submit"
                @click="postComment"
                :disabled="isPostCommentDisabled"
            >
                Post comment
            </button>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.add-commment-input {
    margin-bottom: 1rem;
}
</style>
