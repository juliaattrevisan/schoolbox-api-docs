<script>
import axios from "axios";
import MediaArticle from "@schoolboxVue/components/media-article";
import Storage from "@schoolboxVue/components/storage";
import Toast from "@schoolboxVue/components/toast";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import AddCommentInput from "@schoolboxVue/components/ckeditor/add-comment-input";
import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";

export default {
    components: {
        MediaArticle,
        Storage,
        Toast,
        RevealModal,
        AddCommentInput,
    },

    mixins: [cdnPrefixMixin],

    props: {
        thread: {
            type: Object,
            default: {
                children: {
                    data: [],
                    metadata: {
                        count: null,
                        cursor: null,
                    },
                },
            },
        },
        createThreadEndpoint: {
            type: String,
        },
        canComment: {
            type: Boolean,
        },
        siteVersion: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            commentThread: this.thread,
            isProcessing: false,
            commentIndexToDelete: null,
        };
    },

    computed: {
        dateDisplayFormats: () => window.dateDisplayFormats,

        /**
         * Does this thread exist yet? If not, it will have to be created before
         * creating any posts.
         *
         * @returns {Boolean}
         */
        hasThread() {
            return this?.commentThread?.id;
        },

        /**
         * Does this thread have any comments?
         *
         * @returns {Boolean}
         */
        hasComments() {
            return this.commentTotalCount > 0;
        },

        /**
         * How many comments are currently loaded in this thread?
         *
         * @returns {Number}
         */
        commentLoadedCount() {
            let count = Number(this.commentThread?.children?.data?.length);
            return isNaN(count) ? 0 : count;
        },

        /**
         * How many total comments (beyond the amount currently in the list)
         * are there in this thread?
         *
         * @returns {Number}
         */
        commentTotalCount() {
            let count = Number(this.commentThread?.children?.metadata?.count);
            return isNaN(count) ? 0 : count;
        },

        /**
         * How many total comments (beyond the amount currently in the list)
         * are there in this thread?
         *
         * @returns {Boolean}
         */
        allCommentsLoaded() {
            return this.commentTotalCount === this.commentLoadedCount;
        },

        /**
         * Retrieve a list of comments, even if no thread exists yet.
         *
         * @returns {Array}
         */
        comments() {
            let comments = this.commentThread?.children?.data;
            return comments === null ? [] : comments;
        },
    },

    methods: {
        /**
         * Loads the next cursor of comments.
         */
        loadMoreComments() {
            // What's the cursor?
            const cursor = this.commentThread?.children?.metadata?.cursor?.next;
            const getUrl = this.commentThread?._links?.getComments?.href;
            if (getUrl && cursor) {
                this.$refs.toast.activate("Loading...");
                axios
                    .get(getUrl, { params: { cursor: cursor } })
                    .then((response) => {
                        this.commentThread.children.data = this.commentThread.children.data.concat(response.data.data);
                        this.commentThread.children.metadata.cursor.next = response.data.metadata.cursor.next;
                        this.$refs.toast.timeout("Loaded comments", true, 2000);
                    })
                    .catch(() => this.$refs.toast.timeout("Loading comments failed", false, 5000));
            }
        },

        /**
         * Comment input failed to load, so show error
         */
        onLoadError() {
            this.$refs.toast.timeout("Loading comment box failed", false, 5000);
        },

        /**
         * Posts a comment to this thread.
         * If there's no thread yet: this method will create one, and then
         * post the comment to it.
         *
         * @param { String }
         */
        postComment(newComment) {
            // maybe there's not a thread at all. If not: create one, then post
            let prePromise = Promise.resolve();

            this.isProcessing = true;
            this.$refs.toast.activate("Saving...");

            if (!this.hasThread) {
                prePromise = axios.post(this.createThreadEndpoint, { open: true }).then((response) => {
                    // Set the comment thread to the one just created,
                    // and forge an empty set of comments
                    this.commentThread = response.data;
                    this.commentThread.children = {
                        data: [],
                        metadata: {
                            count: 0,
                            cursor: {
                                current: null,
                                next: null,
                            },
                        },
                    };
                });
            }
            prePromise
                .then(() => axios.post(this.commentThread._links.createComment.href, { content: newComment }))
                .then((response) => {
                    // Update the comment count, add the new comment to the
                    // thread, clear the pending comment box
                    this.commentThread.children.metadata.count += 1;
                    this.commentThread.children.data.unshift(response.data);
                    this.$refs.toast.timeout("Comment posted", true, 2000);
                })
                .catch(() => this.$refs.toast.timeout("Comment post failed", false, 5000))
                .finally(() => (this.isProcessing = false));
        },

        /**
         * Opens the delete modal: pressing the Confirm button in the modal will
         * delete that comment.
         *
         * @param {Number} index
         */
        showDeleteModal(index) {
            this.commentIndexToDelete = index;
            this.$refs.deleteModal.open();
        },

        /**
         * Deletes the comment at the given index within the thread.
         *
         * @param {Number} index
         */
        deleteComment(index) {
            const comment = this.commentThread.children.data[index];
            const deleteLink = comment?._links?.delete?.href;
            this.$refs.deleteModal.close();
            if (deleteLink) {
                this.$refs.toast.activate("Deleting...");
                axios
                    .delete(deleteLink)
                    .then(() => {
                        this.commentThread.children.data.splice(index, 1);
                        this.commentThread.children.metadata.count -= 1;
                        this.$refs.toast.timeout("Deleted", true, 2000);
                    })
                    .catch(() => this.$refs.toast.timeout("Delete failed", false, 5000));
            }
        },

        /**
         * Format the date as specified
         *
         * @param { Date }
         * @param { String }
         *
         * @returns { String }
         */
        formatDate(date, format) {
            return dayjs(date).format(format);
        },

        /**
         * Get the date as from now
         *
         * @param { Date }
         *
         * @returns { String } ie: 2 hours ago
         */
        fromNow(date) {
            return dayjs(date).fromNow();
        },
    },
};
</script>

<template>
    <div class="small-12 island">
        <section class="discussion">
            <div class="discussion-threads">
                <p class="comment-count">
                    Comments <span v-if="!hasComments">(0)</span
                    ><span v-else>({{ commentThread.children.metadata.count }})</span>
                </p>
                <template v-if="canComment">
                    <add-comment-input
                        :cdn-prefix="cdnPrefix"
                        :is-processing="isProcessing"
                        :site-version="siteVersion"
                        @on-load-error="onLoadError"
                        @on-post-comment="postComment"
                    />
                </template>
                <template v-if="hasComments">
                    <ul class="information-list">
                        <li class="actions-small-1" v-for="(comment, index) in comments" :key="index">
                            <div class="list-item">
                                <div class="card small-12 compact">
                                    <!--
                                        Infer that a user is deleted by absence of a profile link.
                                        Note that adding an isDeleted property can't be done easily due to use of
                                        decorators in Evidence/Translator/ModelToArray/Comments->loadAndTranslateCommentsInDiscussionThread().
                                    -->
                                    <component
                                        :is="comment.author._links.profile ? 'a' : 'span'"
                                        :href="comment.author._links.profile ? comment.author._links.profile.href : ''"
                                        title="View Profile"
                                    >
                                        <img :src="comment.author._links.avatar.href" alt="View Profile" />
                                        <p>{{ comment.author.fullName }}</p>
                                        <time
                                            class="pipe meta"
                                            :datetime="formatDate(comment.createdAt.json)"
                                            :title="
                                                formatDate(comment.createdAt.json, dateDisplayFormats.dayjsDatetime)
                                            "
                                            >{{ fromNow(comment.createdAt.json) }}</time
                                        >
                                    </component>
                                </div>
                            </div>
                            <nav>
                                <a
                                    v-if="comment._links.delete.auth"
                                    class="icon-delete"
                                    title="Delete comment"
                                    data-test="learning-moment-evidence-delete-comment"
                                    @click.prevent="showDeleteModal(index)"
                                />
                            </nav>
                            <media-article
                                data-test="learning-moment-evidence-comment-thread-comment"
                                :content="comment.content"
                            ></media-article>
                        </li>
                    </ul>
                    <template
                        v-if="
                            commentThread.children.metadata.count > commentThread.children.data.length &&
                            commentThread.children.metadata.cursor.next
                        "
                    >
                        <a @click.prevent="loadMoreComments" class="article-read-more">Load older comments</a>
                    </template>
                </template>

                <toast ref="toast"></toast>
                <reveal-modal ref="deleteModal">
                    <h2>Please confirm</h2>
                    <p>Are you sure you want to delete this comment?</p>
                    <ul class="flex-list buttons">
                        <li>
                            <button
                                @click.prevent="deleteComment(commentIndexToDelete)"
                                class="button submit"
                                data-test="learning-moment-evidence-delete-comment-confirm"
                            >
                                Delete
                            </button>
                        </li>
                        <li><button @click.prevent="$refs.deleteModal.close" class="button">Cancel</button></li>
                    </ul>
                </reveal-modal>
            </div>
        </section>
    </div>
</template>
