<script>

import axios from 'axios';
import MediaArticle from '@schoolboxVue/components/media-article';
import toastMixin from '@schoolboxVue/components/toast-mixin';
import RevealModal from '@schoolboxVue/components/reveal-modal';
import AddCommentInput from '@schoolboxVue/components/ckeditor/add-comment-input';
import cdnPrefixMixin from '@schoolboxVue/components/cdn-prefix-mixin';


export default {

    name: 'article-threads',

    components: {
        MediaArticle,
        RevealModal,
        AddCommentInput,
    },

    mixins: [
        cdnPrefixMixin,
        toastMixin
    ],

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
            isProcessing: false,
            showCommentsSection: false,
            isInitialLoad: true,
            postHeaders: {
                'X-Requested-With' : 'XMLHttpRequest'
            },
        }
    },

    mounted () {
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
            this.isProcessing = true;

            // Short-circuit if no thread ID
            if (!this.threadId) {
                this.isProcessing = false;
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
                this.toastActivate('Unable to load comments');
                this.toastError('Unable to load comments');
            }).finally(() => {
                if (this.isInitialLoad) {
                    // Anchored? go to the element once the component has been loaded
                    if (window.location.hash === '#discussion') {
                        const discussionElement = document.getElementById('discussion');
                        discussionElement.scrollIntoView( { behavior: 'smooth' } );
                    }
                    this.isInitialLoad = false;
                }
                this.isProcessing = false;
            });
        },

        /**
         * The threads 'Allow Comments' has been toggled
         *
         * @param {event} event  To determine the status
         */
        changeThreadStatus (event) {
            let changeThreadStatusAction;

            this.isThreadEnabled = event.target.checked;

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
                        reject(error ?? '');
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
                        .catch(function (error) {
                            reject(error ?? '');
                        });
                });
            }

            // Actually run the promise, setting the toast accordingly
            changeThreadStatusAction
                .then(() => {
                })
                .catch((error) => {
                    this.toastActivate('Saving...');
                    this.toastError('Unable to save');
                });
        },

        /**
         * Comment input failed to load, so show error
         */
        onLoadError () {
            this.$refs.toast.timeout('Loading comment box failed', false, 5000);
        },

        /**
         * Post a comment
         *
         *  @param { String }
         */
        postComment (newComment) {
            this.isProcessing = true;
            this.toastActivate('Posting comment...');

            axios.post('/discussion/comms/' + this.articleId + '/threads/' + this.threadId + '/comments', {
                content: newComment
            }).then((response) => {

                this.comments.unshift(response.data);
                this.totalCommentsCount++;

                this.toastSuccess('Comment has been added');

            }).catch((error) => {
                this.isProcessing = false;
                this.toastError('Unable to post comment');
            }).finally(() => {
                this.isProcessing = false;
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
            this.isProcessing = true;
            this.toastActivate('Deleting comment...');

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

                this.toastSuccess('Comment deleted');
                this.isProcessing = false;

            }).catch((error) => {
                this.toastError('Unable to delete comment');
                this.isProcessing = false;
            });
        },

        /**
         * Return the date from now,
         *
         * @param { Date }
         *
         * @returns { String } ie: 2 hours ago
         */
        fromNow (date) {
            return dayjs(date.database).fromNow();
        },

        /**
         * Dispaly the full date
         *
         * @param { Date }
         *
         * @returns { String }
         */
        fullString (date) {
            return dayjs(date.database).format(dateDisplayFormats.dayjsDatetime);
        }

    },

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
                            <add-comment-input
                                :cdn-prefix="cdnPrefix"
                                :is-processing="isProcessing"
                                :site-version="siteVersion"
                                @on-load-error="onLoadError"
                                @on-post-comment="postComment"
                            />
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
                                        <span class="pipe meta" :title="fullString(comment.createdAt)">Posted {{ fromNow(comment.createdAt) }}</span>
                                    </a>
                                </div>
                            </div>
                            <nav>
                                <a v-if="comment._links.delete.auth" @click.prevent="showDeleteModal(index)" href="#delete-comment" class="icon-delete no-text" title="Delete comment"></a>
                            </nav>
                            <media-article v-html="comment.content"></media-article>
                        </li>
                    </ul>
                    <button v-if="showLoadMoreButton" @click="getThread" class="button expand" :disabled="isProcessing">Show older comments</button>
                </template>
            </div>
        </section>
        <div id='toaster' class="toast" data-toast></div>
        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
            <p>Are you sure you want to delete this comment?</p>
            <ul class="flex-list buttons">
                <li><button @click.prevent="deleteComment" class="button submit" id="article-threads-delete-button" :disabled="isProcessing">Delete</button></li>
                <li><button @click.prevent="$refs.deleteModal.close" class="button" id="article-threads-cancel-button" :disabled="isProcessing">Cancel</button></li>
            </ul>
        </reveal-modal>
    </div>
</template>

