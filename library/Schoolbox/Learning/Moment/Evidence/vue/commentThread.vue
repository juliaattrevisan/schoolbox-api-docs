<script>

import axios  from 'axios';
import moment from 'moment';

import MediaArticle from '@schoolbox/media-article';
import Storage from '@schoolbox/storage';

export default {
    components: {
        MediaArticle,
        Storage,
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
            showSkeletonLoader: false,
            pendingComment: '',
        }
    },
    computed: {
        dateDisplayFormats: () => window.dateDisplayFormats,
        ckeditorConfig () {
            // Use the same config as the editor in the news does
            return window.jQuery.alaressEditorConfig({
                removePlugins: 'uploadimage,link,schoolboxanchor',
                removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,EqnEditor,Embed',
                toolbarGroups: [
                    {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
                    {name: 'paragraph', groups: ['list', 'blocks', 'bidi']},
                    {name: 'links', groups: ['links']},
                    {name: 'insert', groups: ['insert']},
                    {name: 'schoolbox', groups: ['schoolboxlink']},
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
                axios
                    .get(getUrl, {params: {cursor: cursor}})
                    .then((response) => {
                        self.commentThread.children.data = self.commentThread.children.data.concat(response.data.data);
                        self.commentThread.children.metadata.cursor.next = response.data.metadata.cursor.next;
                    })
            }
        },

        postComment() {
            // maybe there's not a thread at all. If not: create one, then post
            let prePromise = Promise.resolve(),
                self       = this;
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
                    self.commentThread.children.metadata.count += 1;
                    self.commentThread.children.data.unshift(response.data);
                })
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
                <!--
                <li class="actions-small-1" v-if="showSkeletonLoader" v-for="n in 3">
                    <div class="list-item">
                        <div class="small-12 card skeleton">
                            <div class="img"></div>
                            <h3></h3>
                            <p class="meta"></p>
                        </div>
                    </div>
                </li>
                -->
            </ul>
        </template>

        <!-- Comment: This Vue instance has included the CKEditor plugin, so the ckeditor tag exists -->
        <ckeditor v-model="pendingComment" :config="ckeditorConfig"></ckeditor>
        <a class="button submit"
           @click="postComment"
           :disabled="pendingComment.trim() === ''">Post Comment</a>
    </div>
</template>
