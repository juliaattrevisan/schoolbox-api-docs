<script>

import Vue from 'vue';
import axios  from 'axios';
import moment from 'moment';

import MediaArticle from '@vue/components/media-article';
import Storage from '@vue/components/storage';
import Toast from '@vue/components/toast';
import RevealModal from '@vue/components/reveal-modal';
import cdnPrefixMixin from '@vue/components/cdn-prefix-mixin';

export default {
    components: {
        MediaArticle,
        Storage,
        Toast,
        RevealModal
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
                    }
                }
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
            required: true
        }
    },
    data() {
        return {
            commentThread: this.thread,
            pendingComment: '',
            saveInProgress: false,
            commentIndexToDelete: null,
            editorUrl: this.cdnPrefix + '/js/ckeditor/ckeditor.js',
            isCKEditorLoading: false,
            isCKEditorLoaded: false,
            isCKEditorReady: false,
            ckeditorConfig: {
                embed_provider: '//iframe.ly/api/oembed?url={url}&callback={callback}&api_key=94aa8fc28ac1089a2dfb28',
                removePlugins: 'image,uploadimage,link,schoolboxanchor,elementspath,wordcount,autolink,autoembed',
                removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,EqnEditor,Embed',
                toolbarGroups: [
                    {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
                    {name: 'paragraph', groups: ['list', 'blocks', 'bidi']},
                    {name: 'insert', groups: ['insert']}
                ],
                contentsCss: [
                    '/css/core/skin.css.php',
                    this.cdnPrefix + '/css/core/editor.css',
                ],
                height: '4rem',
                resize_enabled: false,
                startupFocus: true,
            }
        }
    },
    computed: {

         /**
         * The text within the comment prompt button, will change to loading when selected to notify
         *
         * @returns {String}
         */
        getAddCommentButtonText () {
            if (this.isCKEditorLoading) return 'Loading';
            else return 'Add a comment'
        },

        dateDisplayFormats: () => window.dateDisplayFormats,

        /**
         * Does this thread exist yet? If not, it will have to be created before
         * creating any posts.
         *
         * @returns {Boolean}
         */
        hasThread () {
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

        /**
         * Ensure there's something (anything) in the comment and that the form is not already processing
         *
         * @returns {Boolean}
         */
        isPostCommentDisabled () {
            if (this.pendingComment.trim() === '' || this.isProcessActive) {
                return true;
            } else {
                return false;
            }
        }

    },

    methods: {


        /**
         * Loads ckeditor only when required rather than on page load
         *
         */
        loadCKEditor () {

            //to replace 'Add a Comment' with 'Loading' once the prompt button is selected
            this.isCKEditorLoading = true;

            const loadScripts = async () => {

                try {
                    
                    await this.injectScript(this.cdnPrefix + '/js/ckeditor/ckeditor.js?v=' + this.siteVersion);
                    await this.injectScript(this.cdnPrefix + '/js/editor/jquery.editor.js?v=' + this.siteVersion);

                    $.fn.alaressEditor({
                        targetElements: $('#comment'),
                        startupFocus: true,
                        height: '8rem',
                        removePlugins: 'uploadimage,schoolboxanchor',
                        removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,Embed',
                        toolbarGroups: [
                            {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
                            {name: 'paragraph', groups: ['list', 'blocks', 'bidi']},
                            {name: 'insert', groups: ['insert']},
                            {name: 'schoolbox', groups: ['schoolboxlink']}
                        ],
                        on: {
                            change: (event) => {
                                this.pendingComment = CKEDITOR.instances['comment'].getData(); // Bind the content of CKEditor into the comment model on change
                            },
                            instanceReady: (event) => { //can display the loaded CKEditor now
                                this.isCKEditorLoading = false;
                                this.isCKEditorLoaded = true;
                            }
                        }
                    });

                } catch (error) {
                    this.$refs.toast.timeout('Loading comments failed', false, 5000);
	            }
            }

            loadScripts();

        },

        /**
         * Async inject a <script> tag into the DOM
         * https://stackoverflow.com/a/39008859
         *
         */
        injectScript (src) {
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.addEventListener('load', resolve);
                script.addEventListener('error', e => reject(e.error));
                document.head.appendChild(script);
            });
        },

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
                    self.$refs.toast.timeout('Comment posted', true, 2000);
                    self.pendingComment = '';
                })
                .catch(() => self.$refs.toast.timeout('Comment post failed', false, 5000))
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
    <div id="discussion" class="small-12 island">
        <section id="threads-section" class="discussion">
            <div class="discussion-threads">
                <p class="comment-count">Comments <span v-if="!hasComments">(0)</span><span v-else>({{ commentThread.children.metadata.count }})</span></p>
                <template v-if="canComment">
                    <button class="btn-show-ckeditor" v-show="!isCKEditorLoaded" @click.once="loadCKEditor">{{ getAddCommentButtonText }}</button>
                    <!-- startupFocus: true on the CKEditor was not working when hiding by more practical display none means... -->
                    <div :style="[isCKEditorLoaded ? { 'position': 'static' } : { 'position': 'absolute', 'left': '-9999px' }]">
                        <textarea id="comment" name="comment" rows="5"></textarea>
                        <a class="button submit"
                            @click="postComment"
                            :disabled="isPostCommentDisabled">Post comment</a>
                    </div>
                </template>
                <template v-if="hasComments">
                    <ul class="information-list">
                        <li class="actions-small-1" v-for="(comment, index) in comments" :key="index">
                            <div class="list-item">
                                <div class="card small-12 compact">
                                    <a :href="comment.author._links.profile.href" title="View Profile">
                                        <img :src="comment.author._links.avatar.href" alt="View Profile" />
                                        <p>{{ comment.author.fullName }}</p>
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
                    <template v-if="commentThread.children.metadata.count > commentThread.children.data.length && commentThread.children.metadata.cursor.next">
                        <a @click.prevent="loadMoreComments" class="article-read-more">Load older comments</a>
                    </template>
                </template>

                <toast ref="toast"></toast>
                <reveal-modal ref="deleteModal">
                    <template>
                        <h2>Please confirm</h2>
                        <p>Are you sure you want to delete this comment?</p>
                        <ul class="flex-list buttons">
                            <li><a @click.prevent="deleteComment(commentIndexToDelete)" class="button submit">Delete</a></li>
                            <li><a @click.prevent="$refs.deleteModal.close" class="button">Cancel</a></li>
                        </ul>
                    </template>
                </reveal-modal>
            </div>
        </section>
    </div>
</template>
