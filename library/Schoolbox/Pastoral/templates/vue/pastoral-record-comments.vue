<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To add and view/delete comments on an existing pastoral record
 * This component manages both 'public' and 'private' recordTypes
 */

import AddCommentInput from "@schoolboxVue/components/ckeditor/add-comment-input";
import MediaArticle from "@schoolboxVue/components/media-article";
import Toast from "@schoolboxVue/components/toast";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";

export default {
    components: {
        AddCommentInput,
        MediaArticle,
        Toast,
        RevealModal,
    },

    mixins: [cdnPrefixMixin],

    props: {
        commentHeading: {
            //can be either 'General' or 'Confidential'
            type: String,
            required: true,
        },
        commentInsertRoute: {
            type: String,
            required: true,
        },
        initialComments: {
            //to display the comment list
            type: Array,
            required: true,
        },
        recordType: {
            //can be either 'public' or 'private'
            type: String,
            required: true,
        },
        siteVersion: {
            //for cache busting the ckeditor load
            type: String,
            required: true,
        },
    },

    data() {
        return {
            comments: [],
            isProcessing: false, //so can't rapid fire clicks on the submit button
            commentToDelete: null,
        };
    },

    created() {
        this.comments = [...this.initialComments]; //as we could be manipulating the comments further, store as data
    },

    methods: {
        /**
         * Comment input failed to load, so show error
         */
        onLoadError() {
            this.$refs.toast.timeout("Loading comment box failed", false, 5000);
        },

        /**
         * Post a comment and add it into the list if successful, otherwise display error
         *
         * @param { String }
         */
        async postComment(newComment) {
            this.isProcessing = true;
            try {
                this.$refs.toast.activate("Saving...");
                const response = await fetch(this.commentInsertRoute, {
                    method: "POST",
                    body: this.getPostCommentFormData(newComment),
                });
                const addedComment = await response.json();
                this.comments = [addedComment, ...this.comments];
                this.$refs.toast.timeout("Comment posted", true, 2000);
            } catch (error) {
                console.warn(error);
                this.$refs.toast.timeout("Unable to post comment", false, 2000);
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * To send with the postComment request
         *
         * @returns { FormData }
         */
        getPostCommentFormData(newComment) {
            const formData = new FormData();
            formData.append("comment_body", newComment);
            if (this.recordType === "private") {
                formData.append("comment_private", "1");
            }
            return formData;
        },

        /**
         * Opens the delete modal: pressing the Confirm button in the modal will
         * delete the chosen comment
         *
         * @param { Number }
         */
        showDeleteModal(commentToDelete) {
            this.commentToDelete = commentToDelete;
            this.$refs.deleteModal.open();
        },

        /**
         * Deletes the comment with the given id within the thread.
         */
        async deleteComment() {
            this.isProcessing = true;
            try {
                this.$refs.toast.activate("Deleting comment...");
                await fetch(this.commentToDelete._links.delete, {
                    method: "POST",
                });
                this.comments = this.comments.filter((comment) => comment.id !== this.commentToDelete.id); //pull out from the comment list
                this.$refs.toast.timeout("Comment deleted", true, 2000);
            } catch (error) {
                console.warn(error);
                this.$refs.toast.timeout("Unable to delete comment", false, 2000);
            } finally {
                this.$refs.deleteModal.close();
                this.isProcessing = false;
            }
        },

        /**
         * Return the date from now,
         *
         * @param { Date }
         *
         * @returns { String } ie: 2 hours ago
         */
        fromNow(date) {
            return dayjs(date).fromNow();
        },

        /**
         * Dispaly the full date
         *
         * @param { Date }
         *
         * @returns { String }
         */
        fullString(date) {
            return dayjs(date).format(dateDisplayFormats.dayjsDatetime);
        },
    },
};
</script>

<template>
    <div class="feed-comments small-12 columns">
        <div class="small-12 island">
            <section class="discussion">
                <div class="discussion-threads">
                    <p class="comment-count">{{ commentHeading }} Comments ({{ comments.length }})</p>

                    <add-comment-input
                        :cdn-prefix="cdnPrefix"
                        :is-processing="isProcessing"
                        :site-version="siteVersion"
                        :preset-unique-id="recordType"
                        @on-load-error="onLoadError"
                        @on-post-comment="postComment"
                    />

                    <transition-group class="information-list" name="list-complete" tag="ul">
                        <li v-for="comment in comments" :key="comment.id" class="actions-small-1 list-complete-item">
                            <div class="list-item">
                                <div class="card compact small-12">
                                    <a :href="comment.owner._links.profile" title="View Profile">
                                        <img :src="comment.owner._links.avatar" title="profile photo" />
                                        {{ comment.owner.fullName }}
                                        <span class="pipe meta" :title="fullString(comment.updatedAt)"
                                            >Posted {{ fromNow(comment.updatedAt) }}</span
                                        >
                                    </a>
                                </div>
                            </div>
                            <nav v-if="comment._links.delete">
                                <a
                                    @click.prevent="showDeleteModal(comment)"
                                    href="#delete-comment"
                                    class="icon-delete no-text"
                                    title="Delete comment"
                                ></a>
                            </nav>
                            <media-article :content="comment.body"></media-article>
                        </li>
                    </transition-group>
                </div>
            </section>
        </div>

        <toast ref="toast"></toast>

        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
            <p>Are you sure you want to delete this comment?</p>
            <ul class="flex-list buttons">
                <li><button @click.prevent="deleteComment" class="button submit">Delete</button></li>
                <li><button @click.prevent="$refs.deleteModal.close" class="button">Cancel</button></li>
            </ul>
        </reveal-modal>
    </div>
</template>

<style scoped>
.list-complete-item {
    transition:
        opacity 0.5s,
        transform 0.5s;
}

.list-complete-enter,
.list-complete-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

.list-complete-leave-active {
    position: absolute;
}
</style>
