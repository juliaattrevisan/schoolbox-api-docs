<script>

import Vue from 'vue';
import moment from 'moment';
import axios from 'axios';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';
import { fetchAsJson } from '@shared/fetch/fetch';

export default {

    components: {
        Toast,
        RevealModal
    },

    props: {
        articleId: Number,
        threadId: Number,
        isThreadCheckboxVisibleOnLoad: Boolean,
        isThreadCheckboxEnabledOnLoad: Boolean,
        isThreadEnabledOnLoad: Boolean,
        isPublished: Boolean,
        createThreadEndpoint: String,
    },

    data: function() {
        return {
            comments: [],
            totalCommentsCount: 0,
            cursorNext: '',
            isThreadCheckboxVisible: undefined,
            isThreadCheckboxEnabled: undefined,
            isThreadEnabled: undefined,
            showSkeletonLoader: true,
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
            editorUrl: "/javascript/ckeditor/ckeditor.js",
            isCKEditorLoading: false,
            isCKEditorLoaded: false,
            isCKEditorReady: false,
            ckeditorConfig: {
                embed_provider: '//iframe.ly/api/oembed?url={url}&callback={callback}&api_key=94aa8fc28ac1089a2dfb28',
                removePlugins: 'image,uploadimage,link,schoolboxanchor,elementspath,wordcount',
                removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,EqnEditor,Embed',
                toolbarGroups: [
                    {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
                    {name: 'paragraph', groups: ['list', 'blocks', 'bidi']},
                    {name: 'links', groups: ['links']},
                    {name: 'insert', groups: ['insert']}
                ],
                contentsCss: [
                    '/css/core/skin.css.php',
                    '/css/core/editor.css',
                ],
                height: '4rem',
                resize_enabled: false,
                startupFocus: true
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
            else return 'Add a Comment'
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
            this.isThreadCheckboxVisible = this.isThreadCheckboxVisibleOnLoad;
            this.isThreadCheckboxEnabled = this.isThreadCheckboxEnabledOnLoad;
            this.isThreadEnabled = this.isThreadEnabledOnLoad; 
        },

        /**
         * Retrieve a list of comments
         *
         */
        getThread () {

            // Short-circuit if no thread ID
            if (!this.threadId) {
                this.isProcessActive = false;
                this.showSkeletonLoader = false;
                this.showCommentsSection = this.isThreadCheckboxVisible;
                return;
            }

            // TODO don't construct this endpoint manually
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

                this.showSkeletonLoader = false; // Hide skeleton loader once the request is done

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
                this.showSkeletonLoader = false;
                this.$refs.toast.activate('Unable to load comments');
                this.$refs.toast.timeout('Unable to load comments', false);
            }).finally(() => {
                /**
                 * Since schoolbox.min.js is being executed first before VueJS content gets rendered,
                 * the schoolboxResource_ProxyReplacement is unable to locate the DOM yet.
                 * With that for a quick fix, we need to read the DOM when the contents has been loaded and let
                 * schoolboxResource_ProxyReplacement do what it's supposed to do.
                 */
                this.reInitialiseMediaPlayer();

                if (this.isInitialLoad) {
                    // Anchored? go to the element once the component has been loaded
                    // We will need to do this for now to make the anchor behaviour more consistent
                    if (window.location.hash === '#discussion') {
                        const offset = $('.banner.content').outerHeight() + $('.article.content').outerHeight() + $('#discussion').offset().top;
                        $("html, body").animate({ scrollTop: offset }, 500);
                    }
                    this.isInitialLoad = false;
                }
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
                    // TODO don't construct this endpoint manually
                    var changeThreadStatusEndpoint = '/discussion/comms/' + this.articleId + '/threads/' + this.threadId + '/';

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
                    // this.loadCkEditor();
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

            //file is referenced from /javascript/components/ckeditor4-vue.bundle.js according to the publicPath and filename props within output in webpack.config.js
            //see https://webpack.js.org/guides/code-splitting/ and https://webpack.js.org/guides/public-path/ (code-splitting) for further details
            import(/* webpackChunkName: "ckeditor4-vue" */ 'ckeditor4-vue')
                .then(({ default: CKEditor }) => {
                    Vue.component('ckeditor', CKEditor.component); //register the component for use once ready
                    this.isCKEditorLoaded = true;
                })
                .catch(err => {
                    this.$refs.toast.timeout('Loading comments failed', false, 5000);
                });

        },

        /**
         * Once CKEditor is ready, we can display it and hide the prompt button
         * 
         */
        onCKEditorReady (event) {
            this.isCKEditorReady = true;
            this.isCKEditorLoading = false;
        },

        /**
         * Comments can contain media elements, 
         * so ensure they display properly
         *
         */
        reInitialiseMediaPlayer: function () {

            Object.keys(this.comments).forEach((key) => {
                let content = $('<div>' + this.comments[key].content + '</div>');
                let replaced = false;
                $(content).each(function (index, element) {
                    replaced = $.fn.schoolboxResource_ReplaceAll($(element));
                    // Before we render the elements into the front-end, let's try to update the Vue comments model
                    // first so that we can easily initialise mediaelementplayer().
                    // And only update those models who's data has been updated.
                    // Basically those elements that has a videos/audios.
                    debugger;
                    if (replaced) {
                        this.comments[key].content = $(element).html();
                    }
                });
            });

            // Need to add this here. Not really sure why this doesn't get executed when setTimeout is not present...
            setTimeout(function() {
                $.fn.schoolboxResource_InitialiseMediaElementPlayer('#threads-section');
            }, 100);

        },

        /**
         * Post a comment
         *
         */
        postComment () {
            
            this.isProcessActive = true;

            this.$refs.toast.activate('Posting comment...');

            // TODO don't construct this endpoint manually
            axios.post('/discussion/comms/' + this.articleId + '/threads/' + this.threadId + '/comments', {
                content: this.newComment
            }).then((response) => {

                let responseData = response.data;
               
                this.comments.unshift(response.data);
                this.totalCommentsCount++;

                this.$refs.toast.timeout('Comment has been added', true);
                
                this.newComment = '';

                this.isProcessActive = false;

            }).catch((error) => {
                this.$refs.toast.timeout('Unable to post comment', false);
                this.isProcessActive = false;
            }).finally(() => {
                this.reInitialiseMediaPlayer();
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

            const commentIdToDelete = this.comments[this.commentIndexToDelete].id;

            this.$refs.toast.activate('Deleting comment...');

            // TODO don't construct this endpoint manually
            axios.delete('/discussion/comms/' + this.articleId + '/comments/' + commentIdToDelete, {}, { headers: this.postHeaders }).then(() => {
                
                this.comments.splice(this.commentIndexToDelete, 1);
                this.totalCommentsCount--;

                this.$refs.toast.timeout('Comment deleted', true);

                // Automatically trigger show previous comments to show the next set of records when the list has gone down to 0
                if (this.comments.length < 1 && this.totalCommentsCount > 0) {
                    this.getThread();
                }

                setTimeout(function() {
                    $.fn.schoolboxResource_InitialiseMediaElementPlayer($('#threads-section'));
                }, 300);

                this.$refs.deleteModal.close();

                this.isProcessActive = false;

            }).catch((error) => {
                this.isProcessActive = false;
                this.$refs.toast.timeout('Unable to delete comment', false);
            });

        }

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
                        <h2>Comments <span v-if="!showSkeletonLoader">({{ totalCommentsCount }})</span></h2>
                    </div>
                    <div v-if="isThreadCheckboxVisible" class="checklist small-6 columns">
                        <input id="allow-comments" name="allowComments" type="checkbox" @click="changeThreadStatus" v-model="isThreadCheckboxEnabled" />
                        <label for="allow-comments">Allow comments</label>
                    </div>
                </div>
                <div v-if="isThreadEnabled">
                    <div v-if="isPublished">
                        <button class="btn-show-ckeditor" v-show="!isCKEditorReady" @click.once="loadCKEditor">{{ getAddCommentButtonText }}</button>
                        <!-- startupFocus: true on the CKEditor was not working when hiding by more practical display none means... -->
                        <div v-if="isCKEditorLoaded" :style="[isCKEditorReady ? { 'position': 'static' } : { 'position': 'absolute', 'left': '-9999px' }]">
                            <ckeditor tag-name="textarea" v-model="newComment" @ready="onCKEditorReady" :editor-url="editorUrl" :config="ckeditorConfig" :read-only="isProcessActive"></ckeditor>
                            <a class="button submit"
                            @click="postComment"
                            :disabled="newComment.trim() === '' || isProcessActive">Post Comment</a>
                        </div>
                    </div>
                    <div v-else>
                        <div data-alert="" class="alert-box">
                            <span class="icon-locked">Comments will be available once this article is published.</span>
                        </div>
                    </div>
                </div>
                <div v-else>
                    <div data-alert="" class="alert-box">
                        <span class="icon-locked">Comments are currently disabled for this article.</span>
                    </div>
                </div>
                <ul v-if="showSkeletonLoader" class="information-list social-stream">
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
                <ul v-else class="information-list social-stream">
                    <li class="actions-small-2" v-for="(comment, index) in comments" :key="index">
                        <div class="list-item">
                            <div class="card compact small-12">
                                <a :href="'/search/user/' + comment.author.id" title="View Profile">
                                    <img :src="'/portrait.php?id=' + comment.author.id + '&size=square64'">
                                    <p class="" meta>
                                        {{ comment.author.fullName }}
                                        <span class="pipe meta" :title="comment.createdAt | fullString ">Posted {{ comment.createdAt | fromNow }}</span>
                                    </p>
                                </a>
                            </div>
                        </div>
                        <nav>
                            <a v-if="comment._links.delete.auth" @click.prevent="showDeleteModal(index)" href="#delete-comment" class="icon-delete no-text" title="Delete comment"></a>
                        </nav>
                        <article v-html="comment.content"></article>
                    </li>
                </ul>
                <a v-if="showLoadMoreButton" @click="getThread" class="button expand" :disabled="isProcessActive">Show older comments</a>
            </div>
        </section>
        <toast ref="toast"></toast>
        <reveal-modal ref="deleteModal">
            <template>
                <h2>Please confirm</h2>
                <p>Are you sure you want to delete this comment?</p>
                <ul class="flex-list buttons">
                    <li><a @click.prevent="deleteComment" class="button submit" :disabled="isProcessActive">Yes</a></li>
                    <li><a @click.prevent="$refs.deleteModal.close" class="button" :disabled="isProcessActive">No</a></li>
                </ul>
            </template>
        </reveal-modal>
    </div>
</template>

