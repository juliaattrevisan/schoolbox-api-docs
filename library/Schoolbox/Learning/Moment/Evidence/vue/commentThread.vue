<script>

import axios  from 'axios';
import moment from 'moment';

import MediaArticle from '@schoolbox/media-article';
import Storage from '@schoolbox/storage';
import Toast from '@schoolbox/toast';
import CKEditor from 'ckeditor4-vue';
import RevealModal from '@schoolbox/reveal-modal';

export default {
    components: {
        MediaArticle,
        Storage,
        Toast,
        ckeditor: CKEditor.component,
        RevealModal
    },
    props: {
        thread: {
            type: Object,
            default: {
                children: {
                    data: [],
                    metadata: {
                        count: null,
                        cursor: null,
                    }
                }
            },
        },
        createThreadEndpoint: {
            type: String,
        }
    },
    data() {
        return {
            commentThread: this.thread,
            pendingComment: '',
            saveInProgress: false,
            commentIndexToDelete: null,
        }
    },
    computed: {
        dateDisplayFormats: () => window.dateDisplayFormats,
        ckeditorConfig () {
            // Use the same config as the news comment thread does.
            // Somehow: if the "eqneditor" plugin is not present, or its button
            // is removed, then embedding an image fails!
            // I don't yet know how to work around this, so for now I've just
            // removed all the Schoolboxlink plugins: these should be plain text
            // anyway.
            return window.jQuery.alaressEditorConfig({
                removePlugins: 'uploadimage,link,schoolboxanchor',
                removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,EqnEditor,Embed',
                toolbarGroups: [
                    {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
                    {name: 'paragraph', groups: ['list', 'blocks', 'bidi']},
                    {name: 'links', groups: ['links']},
                    {name: 'insert', groups: ['insert']},
                    // {name: 'schoolbox', groups: ['schoolboxlink']},
                ],
            });
        },

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
            let cursor = this.commentThread?.children?.metadata?.cursor?.next,
                getUrl = this.commentThread?._links?.getComments?.href,
                self   = this;
            if (getUrl && cursor) {
                self.$refs.toast.activate('Loading...');
                axios
                    .get(getUrl, {params: {cursor: cursor}})
                    .then((response) => {
                        self.commentThread.children.data = self.commentThread.children.data.concat(response.data.data);
                        self.commentThread.children.metadata.cursor.next = response.data.metadata.cursor.next;
                        this.$refs.toast.timeout('Loaded comments', true, 2000);
                    })
                    .catch(() => self.$refs.toast.timeout('Loading comments failed', false, 5000))
            }
        },


        /**
         * Posts a comment to this thread.
         *
         * If there's no thread yet: this method will create one, and then
         * post the comment to it.
         */
        postComment() {
            // maybe there's not a thread at all. If not: create one, then post
            let prePromise = Promise.resolve(),
                self       = this;

            self.saveInProgress = true;
            self.$refs.toast.activate('Saving...');

            if (!this.hasThread) {
                prePromise = axios
                    .post(this.createThreadEndpoint, {open: true})
                    .then((response) => {
                        // Set the comment thread to the one just created,
                        // and forge an empty set of comments
                        self.commentThread = response.data;
                        self.commentThread.children = {
                            data: [],
                            metadata: {
                                count: 0,
                                cursor: {
                                    current: null,
                                    next: null,
                                }
                            }
                        };
                    })
            }
            prePromise
                .then(() => axios.post(self.commentThread._links.createComment.href, {content: self.pendingComment}))
                .then((response) => {
                    // Update the comment count, add the new comment to the
                    // thread, clear the pending comment box
                    self.commentThread.children.metadata.count += 1;
                    self.commentThread.children.data.unshift(response.data);
                    self.$refs.toast.timeout('Saved', true, 2000);
                    self.pendingComment = '';
                })
                .catch(() => self.$refs.toast.timeout('Save failed', false, 5000))
                .finally(() => self.saveInProgress = false)
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
            let self       = this,
                comment    = self.commentThread.children.data[index],
                deleteLink = comment?._links?.delete?.href;
            self.$refs.deleteModal.close();
            if (deleteLink) {
                self.$refs.toast.activate('Deleting...');
                axios
                    .delete(deleteLink)
                    .then(() => {
                        self.commentThread.children.data.splice(index, 1);
                        self.commentThread.children.metadata.count -= 1;
                        self.$refs.toast.timeout('Deleted', true, 2000);
                    })
                    .catch(() => self.$refs.toast.timeout('Delete failed', false, 5000))
            }
        }
    },
    filters: {
        dateFormat: (date, format) => moment(date).format(format),
        fromNow:    (date)         => moment(date).fromNow(),
    },
}
</script>

<template>
    <div>
        <h3 v-if="!hasComments">
            No comments yet
        </h3>
        <template v-else>
            <span class="comment-count">
                {{ commentThread.children.metadata.count }} comment(s)
                <template v-if="commentThread.children.metadata.count > commentThread.children.data.length && commentThread.children.metadata.cursor.next">
                    (<a @click.prevent="loadMoreComments">Load more</a>)
                </template>
            </span>

            <ckeditor tag-name="textarea" v-model="pendingComment" :config="ckeditorConfig" :read-only="saveInProgress"></ckeditor>
            <a class="button submit"
               @click="postComment"
               :disabled="pendingComment.trim() === '' || saveInProgress">Post Comment</a>

            <ul class="information-list social-stream">
                <li class="actions-small-1" v-for="(comment, index) in comments">
                    <div class="list-item">
                        <div class="card small-12 compact">
                            <a :href="comment.author._links.profile.href" title="View Profile">
                                <img :src="comment.author._links.avatar.href" alt="View Profile" />
                                <p>{{ comment.author.firstname }} {{ comment.author.lastname }}</p>
                                <time class="pipe meta"
                                      :datetime="comment.createdAt.json | dateFormat"
                                      :title="comment.createdAt.json | dateFormat(dateDisplayFormats.momentJsDatetime)"
                                >{{ comment.createdAt.json | fromNow }}</time>
                            </a>
                        </div>
                    </div>
                    <nav>
                        <a v-if="comment._links.delete.auth"
                           @click.prevent="showDeleteModal(index)"
                           class="icon-delete"
                           title="Delete comment" />
                    </nav>
                    <media-article v-html="comment.content"></media-article>
                </li>
            </ul>
        </template>

        <toast ref="toast"></toast>

        <reveal-modal ref="deleteModal">
            <template>
                <h2>Please confirm</h2>
                <p>Are you sure you want to delete this comment?</p>
                <ul class="flex-list buttons">
                    <li><a @click.prevent="deleteComment(commentIndexToDelete)" class="button icon-approve submit">OK</a></li>
                    <li><a @click.prevent="$refs.deleteModal.close" class="button icon-close">Cancel</a></li>
                </ul>
            </template>
        </reveal-modal>
    </div>
</template>
