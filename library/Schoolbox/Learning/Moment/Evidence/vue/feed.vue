<script>

import axios  from 'axios';
import moment from 'moment';

import MediaArticle from '@schoolbox/media-article';
import Storage      from '@schoolbox/storage';

export default {
    components: {
        MediaArticle,
        Storage,
    },
    data() {
        return {
            data:     [],
            metadata: {
                count:  null,
                cursor: null,
            },
            showSkeletonLoader: true,
        }
    },
    created() {
    },
    computed: {
        config: () => window.learningEvidenceFeedConfig,
        dateDisplayFormats: () => window.dateDisplayFormats,
        groupedEvidence() {
            return this.groupEvidence(this.data);
        },
        currentCount () {
            return data.length;
        },
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
    },
    mounted() {
        this.load();
    },
    methods: {
        /**
         * Load learning evidence for the student in question.
         *
         * Once received, group them by date.
         */
        load() {
            this.data = [];
            this.append(null);
        },

        /**
         * Appends learning evidence to the data list.
         */
        append(cursor) {
            let params = {};
            if (cursor) {
                params.cursor = cursor;
            }
            axios
                .get(this.config.routes.getEvidence, {params})
                .then(response => {
                    this.data            = this.data.concat(response?.data?.data ? response.data.data : [])
                    this.metadata.count  = response?.data?.metadata?.count  ? response.data.metadata.count  : 0;
                    this.metadata.cursor = response?.data?.metadata?.cursor ? response.data.metadata.cursor : null;
                })
                .finally(() => this.showSkeletonLoader = false);
        },

        /**
         * Groups learning evidence by the date which it was posted.
         *
         * @param {Array} evidence
         * @return Array
         */
        groupEvidence(evidence) {
            let groupedEvidenceByDate = {};
            evidence.forEach(v => {
                let createdAt = moment(v.createdAt),
                    updatedAt = moment(v.updatedAt),
                    key       = createdAt.format('YYYYMMDD');
                if (!(key in groupedEvidenceByDate)) {
                    groupedEvidenceByDate[key] = {
                        date:     moment(createdAt).startOf('d'),
                        evidence: []
                    }
                }
                v.createdAt = createdAt;
                v.updatedAt = updatedAt;
                groupedEvidenceByDate[key].evidence.push(v);
            });

            // Flatten grouped evidence into an array
            let groupedEvidence = Object.values(groupedEvidenceByDate).sort(
                (a, b) => a.date.isBefore(b.date) ? 1 : -1
            );

            // Ensure that each evidence within the group is sorted
            groupedEvidence.forEach(v => {
                v.evidence.sort(
                    (a, b) => a.createdAt.isBefore(b.createdAt) ? 1 : -1
                )
            });

            return groupedEvidence;
        },

        /**
         * Renders the created-at time as a string.
         *
         * @param {Moment} createdAt
         */
        renderCreatedAt(createdAt) {
            let now     = moment().startOf('d'),
                dayDiff = createdAt.diff(now, 'd');
            if (dayDiff === 0) {
                return 'today';
            }
            if (dayDiff === -1) {
                return 'yesterday';
            }
            if (dayDiff === 1) {
                return 'tomorrow';
            }
            return moment.duration(dayDiff, 'd').humanize(true);
        },

        /**
         * How many comments are currently loaded in this thread?
         *
         * @param discussionThread
         * @returns {number}
         */
        commentLoadedCount(discussionThread) {
            let count = Number(discussionThread?.children?.data?.length);
            return isNaN(count) ? 0 : count;
        },

        /**
         * How many total comments (beyond the amount currently in the list)
         * are there in this thread?
         *
         * @param discussionThread
         * @returns {number}
         */
        commentTotalCount(discussionThread) {
            let count = Number(discussionThread?.children?.metadata?.count);
            return isNaN(count) ? 0 : count;
        },

        /**
         * How many total comments (beyond the amount currently in the list)
         * are there in this thread?
         *
         * @param discussionThread
         * @returns {boolean}
         */
        allCommentsLoaded(discussionThread) {
            return this.commentTotalCount(discussionThread) === this.commentLoadedCount(discussionThread);
        },

        /**
         *
         * @param discussionThread
         * @returns {Array}
         */
        comments(discussionThread) {
            let comments = discussionThread?.children?.data;
            return comments === null ? [] : comments;
        },

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
        <div class="content" v-for="evidenceGroup in groupedEvidence">
            <h2 class="subheader">
                <time :datetime="evidenceGroup.date.format('YYYY-MM-DD')"
                      :title="evidenceGroup.date.format(dateDisplayFormats.momentJsDateLong)"
                >{{ renderCreatedAt(evidenceGroup.date) }}</time>
            </h2>

            <section>
                <ul class="information-list">
                    <li v-for="evidence in evidenceGroup.evidence">
                        <div class="card small-12 compact">
                            <a :href="evidence.creator._links.profile" title="View Profile">
                                <img :src="evidence.creator._links.avatar" alt="View Profile">
                                <p>{{ evidence.creator.fullName }}</p>
                                <time class="pipe meta"
                                      :datetime="evidence.createdAt | dateFormat"
                                      :title="evidence.createdAt  | dateFormat(dateDisplayFormats.momentJsDatetime)"
                                >{{ evidence.createdAt | dateFormat(dateDisplayFormats.momentJsTime) }}</time>
                            </a>
                        </div>

                        <div class="small-12 large-6 column">
                            <!-- Display evidence body, with media players initialised -->
                            <media-article v-if="evidence.body">
                                <div v-html="evidence.body" />
                            </media-article>


                            <!-- Is there an attachment? Display it appropriately -->
                            <article v-if="evidence.filename && evidence.storage">
                                <storage :storage="evidence.storage" :filename="evidence.filename"></storage>
                            </article>
                        </div>

                        <div class="small-12 large-6 column">
                            <!-- Comments -->
                            <span v-if="commentTotalCount(evidence.discussionThread) === 0">
                                No comments yet.
                            </span>
                            <span v-else>
                                {{ commentTotalCount(evidence.discussionThread) }} comments
                                (<a @click.prevent="loadMoreComments(evidence.discussionThread)">Load more</a>)
                            </span>


                            <ul class="information-list social-stream">
                                <li class="actions-small-0" v-for="(comment, index) in comments(evidence.discussionThread)">
                                    <div class="list-item">
                                        <div class="card small-12 compact">
                                            <a :href="evidence.creator._links.profile.href" title="View Profile">
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
                                    <article v-html="comment.content"></article>
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

                            <!-- Comment: This Vue instance has included the CKEditor plugin, so the ckeditor tag exists -->
                            <ckeditor v-model="evidence.pendingComment" :config="ckeditorConfig"></ckeditor>
                        </div>

                        <div class="content">
                            <p><a :href="evidence._links.detail">More Detail</a></p>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <section v-if="showSkeletonLoader">
            <div v-for="n in 3" class="list-item">
                <div class="small-12 card skeleton">
                    <div class="img"></div>
                    <h3></h3>
                    <p class="meta"></p>
                </div>
            </div>
        </section>

        <div class="row" v-else>
            <div class="small-12 island">
                <div v-if="metadata.count === 0" class="empty-state">
                    <i class="icon-favourite"></i>
                    <p>{{ config.student.firstname }} has no learning moments yet.</p>
                </div>
                <button v-else-if="metadata.cursor.next && metadata.count > data.length" class="expand" @click.prevent="append(metadata.cursor.next)">
                    Load more
                </button>
            </div>
        </div>
    </div>

</template>
