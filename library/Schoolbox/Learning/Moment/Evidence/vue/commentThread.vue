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
        commentThread: {
            type: Object,
            default: {},
        },
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
        hasComments() {
            return this.commentTotalCount > 0;
        },

        /**
         * How many comments are currently loaded in this thread?
         *
         * @returns {number}
         */
        commentLoadedCount() {
            let count = Number(this.commentThread?.children?.data?.length);
            return isNaN(count) ? 0 : count;
        },

        /**
         * How many total comments (beyond the amount currently in the list)
         * are there in this thread?
         *
         * @returns {number}
         */
        commentTotalCount() {
            let count = Number(this.commentThread?.children?.metadata?.count);
            return isNaN(count) ? 0 : count;
        },

        /**
         * How many total comments (beyond the amount currently in the list)
         * are there in this thread?
         *
         * @returns {boolean}
         */
        allCommentsLoaded() {
            return this.commentTotalCount === this.commentLoadedCount;
        },

        /**
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
        loadMoreComments(discussionThread) {
            //
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
        <span v-else>
            {{ commentTotalCount }} comments
            <template v-if="!allCommentsLoaded">
                (<a @click.prevent="loadMoreComments">Load more</a>)
            </template>
        </span>


        <ul class="information-list social-stream">
            <li class="actions-small-0" v-for="(comment, index) in comments">
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
<!--                                        <a href="#" class="icon-delete no-text" data-reveal-id="confirm-delete" title="Delete comment" v-if="comment._links.delete.auth" v-on:click="deleteComment(comment.id, index)"></a>-->
<!--                                        {#<a href="#" class="icon-reply no-text" title="Add Reply"></a>#}-->
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
    </div>
</template>
