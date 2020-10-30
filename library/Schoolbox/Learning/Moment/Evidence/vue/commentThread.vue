<script>

import axios  from 'axios';
import moment from 'moment';

import MediaArticle from '@schoolbox/media-article';
import Storage from '@schoolbox/storage';
import Toast from '@schoolbox/toast';
import CKEditor from 'ckeditor4-vue';

export default {
    components: {
        MediaArticle,
        Storage,
        Toast,
        ckeditor: CKEditor.component,
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
    // mounted() {
    //     this.load();
    // },
    methods: {
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
                        this.$refs.toast.timeout('Loaded', true, 2000);
                    })
                    .catch(() => self.$refs.toast.timeout('Loading failed', false, 5000))
            }
        },

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
    },
    filters: {
        dateFormat: (date, format) => moment(date).format(format),
        fromNow:    (date)         => moment(date).fromNow(),
    },
}
</script>

<template>
    <div>
        <span v-if="!hasComments">
            No comments yet.
        </span>
        <template v-else>
            <span>
                {{ commentThread.children.metadata.count }} comments
                <template v-if="commentThread.children.metadata.count > commentThread.children.data.length && commentThread.children.metadata.cursor.next">
                    (<a @click.prevent="loadMoreComments">Load more</a>)
                </template>
            </span>
            <ul class="information-list social-stream">
                <li class="actions-small-0" v-for="comment in comments">
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
                        <!-- Delete will go here -->
                    </nav>
                    <media-article v-html="comment.content"></media-article>
                </li>
            </ul>
        </template>

        <ckeditor tag-name="textarea" v-model="pendingComment" :config="ckeditorConfig" :read-only="saveInProgress"></ckeditor>
        <a class="button submit"
           @click="postComment"
           :disabled="pendingComment.trim() === '' || saveInProgress">Post Comment</a>

        <toast ref="toast"></toast>
    </div>
</template>
