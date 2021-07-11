<script>

import moment from 'moment';
import axios from 'axios';
import MediaArticle from '@schoolbox/media-article';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';
import cdnPrefixMixin from '@schoolbox/cdn-prefix-mixin';

export default {

    name: 'article-threads',

    components: {
        MediaArticle,
        Toast,
        RevealModal
    },

    mixins: [cdnPrefixMixin],

    props: {
        articleId: Number,
        threadIdOnLoad: Number,
        isThreadCheckboxVisibleOnLoad: Boolean,
        isThreadCheckboxEnabledOnLoad: Boolean,
        isThreadEnabledOnLoad: Boolean,
        isPublished: Boolean,
        createThreadEndpoint: String,
        siteVersion: String, //for cache busting
    },

    data () {
        return {
            threadId: 0,
            isThreadCheckboxVisible: undefined,
            isThreadCheckboxEnabled: undefined,
            isThreadEnabled: undefined,
            comments: [],
            totalCommentsCount: 0,
            cursorNext: '',
            showLoadMoreButton: false,
            commentIndexToDelete: undefined,
            confirmDeleteButton: true,
            newComment: '',
            isProcessActive: false,
            showCommentsSection: false,
            isInitialLoad: true,
            postHeaders: {
                'X-Requested-With' : 'XMLHttpRequest'
            },
            isCKEditorLoading: false,
            isCKEditorLoaded: false
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

        /**
         * Ensure there's something (anything) in the comment and that the form is not already processing
         *
         * @returns {Boolean}
         */
        isPostCommentDisabled () {
            if (this.newComment.trim() === '' || this.isProcessActive) {
                return true;
            } else {
                return false;
            }
        }

    },

    mounted: function() {
        this.initDataFromProps();
        this.getThread();
    },

    methods: {

        /**
         * Setup data that is passed through as Props
         *
         */
        initDataFromProps () {
            this.threadId = this.threadIdOnLoad;
            this.isThreadCheckboxVisible = this.isThreadCheckboxVisibleOnLoad;
            this.isThreadCheckboxEnabled = this.isThreadCheckboxEnabledOnLoad;
            this.isThreadEnabled = this.isThreadEnabledOnLoad;
        },

        /**
         * Retrieve a list of comments
         *
         */
        getThread () {

            this.isProcessActive = true;

            // Short-circuit if no thread ID
            if (!this.threadId) {
                this.isProcessActive = false;
                this.isInitialLoad = false;
                this.showCommentsSection = this.isThreadCheckboxVisible;
                return;
            }

            axios.get('/discussion/comms/' + this.articleId + '/threads/' + this.threadId + '/comments/', {
                params: {
                    cursor: this.cursorNext
                }
            }).then((response) => {

                const responseData = response.data;

                responseData.data.forEach((data) => {
                    this.comments.push(data);
                });

                this.totalCommentsCount = responseData.metadata.count;
                this.cursorNext = responseData.metadata.cursor.next;

                /**
                 * TODO: Maybe the API should return a metadata that will let the front-end know if there's still
                 * a next page to render rather than comparing the value of the length of array and the comment's total count?
                 *
                 * Do not show load more button when all the comments has been loaded
                 */
                this.showLoadMoreButton = this.comments.length < responseData.metadata.count;

                /**
                 * If the user has the rights to modify the thread, despite that there's comments,
                 * comments section must be rendered
                 */
                this.showCommentsSection = (this.isThreadCheckboxVisible || this.totalCommentsCount > 0 || this.isThreadEnabled);

            }).catch((error) => {
                this.isInitialLoad = false;
                this.$refs.toast.activate('Unable to load comments');
                this.$refs.toast.timeout('Unable to load comments', false);
            }).finally(() => {
                if (this.isInitialLoad) {
                    // Anchored? go to the element once the component has been loaded
                    if (window.location.hash === '#discussion') {
                        const discussionElement = document.getElementById('discussion');
                        discussionElement.scrollIntoView( { behavior: 'smooth' } );
                    }
                    this.isInitialLoad = false;
                }
                this.isProcessActive = false;
            });
        },

        /**
         * The threads 'Allow Comments' has been toggled
         *
         * @param {event} to determine the status
         */
        changeThreadStatus (event) {

            this.isThreadEnabled = event.target.checked;

            let changeThreadStatusAction;
            if (this.threadId) {
                // I already have a thread ID: switch it on/off
                changeThreadStatusAction = new Promise((resolve, reject) => {

                    let changeThreadStatusEndpoint = '/discussion/comms/' + this.articleId + '/threads/' + this.threadId + '/';
                    if (this.isThreadEnabled) {
                        changeThreadStatusEndpoint += 'open';
                    } else {
                        changeThreadStatusEndpoint += 'close';
                    }

                    axios.put(changeThreadStatusEndpoint, {}, { headers: this.postHeaders }).then(() => {
                        resolve();
                    }).catch(function (error) {
                        reject(error);
                    });
                });
            } else {
                // No thread ID.
                // 1: create a new thread
                // 2: associate it with this context
                // 3: set this object's thread ID
                changeThreadStatusAction = new Promise((resolve, reject) => {
                    axios
                        .post(this.createThreadEndpoint, { open: this.isThreadEnabled }, { headers: this.postHeaders })
                        .then((response) => {
                            this.threadId = response.data.id;
                            resolve();
                        })
                        .catch(() => {
                            reject(error);
                        });
                });
            }

            // Actually run the promise, setting the toast accordingly
            this.$refs.toast.activate('Saving...');
            changeThreadStatusAction
                .then(() => {
                    this.$refs.toast.timeout('Saved', true);
                })
                .catch((error) => {
                    this.$refs.toast.timeout('Unable to save', false);
                });

        },

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
                        removePlugins: 'uploadimage,link,schoolboxanchor,autolink,autoembed',
                        removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,EqnEditor,Embed',
                        toolbarGroups: [
                            {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
                            {name: 'paragraph', groups: ['list', 'blocks', 'bidi']},
                            {name: 'links', groups: ['links']},
                            {name: 'insert', groups: ['insert']},
                            {name: 'schoolbox', groups: ['schoolboxlink']}
                        ],
                        on: {
                            change: (event) => {
                                this.newComment = CKEDITOR.instances['comment'].getData(); // Bind the content of CKEditor into the comment model on change
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
         * Post a comment
         *
         */
        postComment () {

            this.isProcessActive = true;
            this.$refs.toast.activate('Posting comment...');

            axios.post('/discussion/comms/' + this.articleId + '/threads/' + this.threadId + '/comments', {
                content: this.newComment
            }).then((response) => {

                this.comments.unshift(response.data);
                this.totalCommentsCount++;

                this.newComment = '';
                CKEDITOR.instances['comment'].setData(this.newComment); //clear out the CKEditor for another possible post

                this.$refs.toast.timeout('Comment has been added', true);

            }).catch((error) => {
                this.isProcessActive = false;
                this.$refs.toast.timeout('Unable to post comment', false);
            }).finally(() => {
                this.isProcessActive = false;
            });

        },


        /**
         * Opens the delete modal: pressing the Confirm button in the modal will
         * delete that comment.
         *
         * @param {Number} index of the post to delete
         */
        showDeleteModal(index) {
            this.commentIndexToDelete = index;
            this.$refs.deleteModal.open();
        },

        /**
         * Delete a comment
         *
         * @param {event} to determine the status
         */
        deleteComment () {

            this.isProcessActive = true;
            this.$refs.toast.activate('Deleting comment...');

            const commentIdToDelete = this.comments[this.commentIndexToDelete].id;

            // Need the commentIdToDelete, so needs to be constructed manually?
            axios.delete('/discussion/comms/' + this.articleId + '/comments/' + commentIdToDelete, {}, { headers: this.postHeaders }).then(() => {

                this.comments.splice(this.commentIndexToDelete, 1);
                this.totalCommentsCount--;

                // Automatically trigger show previous comments to show the next set of records when the list has gone down to 0
                if (this.comments.length < 1 && this.totalCommentsCount > 0) {
                    this.getThread();
                }

                this.$refs.deleteModal.close();

                this.$refs.toast.timeout('Comment deleted', true);
                this.isProcessActive = false;

            }).catch((error) => {
                this.$refs.toast.timeout('Unable to delete comment', false);
                this.isProcessActive = false;
            });

        },

    },

    filters: {
        fromNow: function (date) {
            return moment(date.database).fromNow();
        },
        fullString: function (date) {
            return moment(date.database).format(dateDisplayFormats.momentJsDatetime);
        }
    }

}
</script>

<template>
    <div>
        <section v-if="showCommentsSection" class="discussion">
            <div class="discussion-threads">
                <div class="row">
                    <div class="small-6 columns">
                        <h2>Comments <span v-if="!isInitialLoad">({{ totalCommentsCount }})</span></h2>
                    </div>
                    <div v-if="!isInitialLoad && isThreadCheckboxVisible" class="checklist small-6 columns">
                        <input id="allow-comments" name="allowComments" type="checkbox" @click="changeThreadStatus" v-model="isThreadCheckboxEnabled" />
                        <label for="allow-comments">Allow comments</label>
                    </div>
                </div>
                <template v-if="!isInitialLoad">
                    <div v-show="isThreadEnabled">
                        <template v-if="isPublished">
                            <button class="btn-show-ckeditor" v-show="!isCKEditorLoaded" @click.once="loadCKEditor">{{ getAddCommentButtonText }}</button>
                            <!-- startupFocus: true on the CKEditor was not working when hiding by more practical display none means... -->
                            <div :style="[isCKEditorLoaded ? { 'position': 'static' } : { 'position': 'absolute', 'left': '-9999px' }]">
                                <textarea id="comment" name="comment" rows="5"></textarea>
                                <a class="button submit"
                                    @click="postComment"
                                    :disabled="isPostCommentDisabled">Post comment</a>
                            </div>
                        </template>
                        <template v-else>
                            <div data-alert="" class="alert-box">
                                <span class="icon-locked">Comments will be available once this article is published.</span>
                            </div>
                        </template>
                    </div>
                    <template v-if="!isThreadEnabled">
                        <div data-alert="" class="alert-box">
                            <span class="icon-locked">Comments are currently disabled for this article.</span>
                        </div>
                    </template>
                </template>
                <template v-if="isInitialLoad">
                    <ul class="information-list">
                        <li class="actions-small-1" v-for="n in 3" :key="n">
                            <div class="list-item">
                                <div class="small-12 card skeleton">
                                    <div class="img"></div>
                                    <h3></h3>
                                    <p class="meta"></p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </template>
                <template v-else>
                    <ul class="information-list">
                        <li class="actions-small-2" v-for="(comment, index) in comments" :key="comment.id">
                            <div class="list-item">
                                <div class="card compact small-12">
                                    <a :href="'/search/user/' + comment.author.id" title="View Profile">
                                        <img :src="'/portrait.php?id=' + comment.author.id + '&size=square64'">
                                        {{ comment.author.fullName }}
                                        <span class="pipe meta" :title="comment.createdAt | fullString ">Posted {{ comment.createdAt | fromNow }}</span>
                                    </a>
                                </div>
                            </div>
                            <nav>
                                <a v-if="comment._links.delete.auth" @click.prevent="showDeleteModal(index)" href="#delete-comment" class="icon-delete no-text" title="Delete comment"></a>
                            </nav>
                            <media-article v-html="comment.content"></media-article>
                        </li>
                    </ul>
                    <a v-if="showLoadMoreButton" @click="getThread" class="button expand" :disabled="isProcessActive">Show older comments</a>
                </template>
            </div>
        </section>
        <toast ref="toast"></toast>
        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
            <p>Are you sure you want to delete this comment?</p>
            <ul class="flex-list buttons">
                <li><a @click.prevent="deleteComment" class="button submit" id="article-threads-delete-button" :disabled="isProcessActive">Delete</a></li>
                <li><a @click.prevent="$refs.deleteModal.close" class="button" id="article-threads-cancel-button" :disabled="isProcessActive">Cancel</a></li>
            </ul>
        </reveal-modal>
    </div>
</template>

