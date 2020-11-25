<script>

import axios  from 'axios';
import moment from 'moment';

import MediaArticle from '@schoolbox/media-article';
import Storage      from '@schoolbox/storage';
import CommentThread from './commentThread';
import RevealModal from "@schoolbox/reveal-modal";
import Toast from "@schoolbox/toast";

export default {
    components: {
        Toast,
        RevealModal,
        MediaArticle,
        Storage,
        CommentThread,
    },
    data() {
        return {
            data:     [],
            metadata: {
                count:  null,
                cursor: null,
            },
            showSkeletonLoader: true,
            currentlyDeletingEvidence: null,
        }
    },
    created() {
    },
    computed: {
        jQuery: () => window.jQuery,
        config: () => window.learningEvidenceFeedConfig,
        dateDisplayFormats: () => window.dateDisplayFormats,
        groupedEvidence() {
            return this.groupEvidence(this.data);
        },
        currentCount () {
            return data.length;
        },
    },
    mounted() {
        this.load();
    },
    updated() {
        // Re-apply Foundation dropdowns
        this.$nextTick(() => this.jQuery(this.$el).foundation('dropdown', 'reflow'));
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
         * Open the share learning moment modal.
         */
        openShareLearningMomentModal() {
            // shareLearningMoment
            this.jQuery('#shareLearningMoment').foundation('reveal', 'open');
        },


        /**
         * Does this evidence have a menu?
         *
         * @param evidence
         * @returns {Boolean}
         */
        hasMenu(evidence) {
            return Boolean(evidence?._links?.modifyForm || evidence?._links?.deleteForm)
        },


        /**
         * Display the delete modal for a piece of evidence.
         *
         * @param {Object} evidence
         */
        showDeleteModal(evidence) {
            this.currentlyDeletingEvidence = evidence;
            this.$refs.deleteModal.open();
        },


        /**
         * Actually delete this evidence.
         *
         * @param {Object} evidence
         */
        deleteEvidence(evidence) {
            this.$refs.toast.activate('Deleting...');

            // Replacing the data in place in the same tick causes issues with
            // the MediaElement player and Foundation dropdowns for some reason.
            // Clear it first; then replace it with the filtered data in the
            // next tick.
            let newData = this.data.filter((v) => v.id !== evidence.id);
            axios
                .delete(evidence._links.delete)
                .then(() => {
                    this.data = [];
                    this.$refs.toast.timeout('Deleted', true);
                    this.$nextTick(() => {
                        this.data = newData;
                        this.metadata.count  -= 1;
                    });
                })
                .catch(() => this.$refs.toast.timeout('Could not delete', false))
                .finally(() => {
                    this.$refs.deleteModal.close();
                })
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
        <div v-for="evidenceGroup in groupedEvidence">
            <h2 class="subheader feed no-margin">
                <time :datetime="evidenceGroup.date | dateFormat('YYYY-MM-DD')"
                      :title="evidenceGroup.date | dateFormat(dateDisplayFormats.momentJsDateLong)"
                >{{ renderCreatedAt(evidenceGroup.date) }}</time>
            </h2>

            <section>
                <ul class="information-list">
                    <li v-for="evidence in evidenceGroup.evidence" class="feed-item">
                        <!-- This evidence isn't necessarily editable -->
                        <div :class="{'actions-small-1': hasMenu(evidence), 'actions-small-0': !hasMenu(evidence)}">
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
                            <nav v-if="hasMenu(evidence)">
                                <a href="#"
                                   class="icon-overflow"
                                   :data-dropdown="'evidence-' + String(evidence.id) + '-dropdown'"
                                   aria-expanded="false" title="Actions"></a>
                            </nav>
                            <ul v-if="hasMenu(evidence)" :id="'evidence-' + String(evidence.id) + '-dropdown'" class="f-dropdown" data-dropdown-content>
                                <li><a v-if="evidence._links.modifyForm" :href="evidence._links.modifyForm" class="icon-modify" title="Modify">Modify</a></li>
                                <li><a @click.prevent="showDeleteModal(evidence)" v-if="evidence._links.delete" class="icon-delete" title="Delete">Delete</a></li>
                            </ul>
                        </div>

                        <div class="feed-body small-12 large-6 column">
                            <!-- Is there an attachment? Display it appropriately -->
                            <article v-if="evidence.filename && evidence.storage">
                                <storage :storage="evidence.storage" :filename="evidence.filename"></storage>
                            </article>

                            <!-- Display evidence body, with media players initialised -->
                            <media-article v-if="evidence.body">
                                <div v-html="evidence.body" />
                            </media-article>

                            <div class="article-read-more">
                                <a :href="evidence._links.detail">More Detail</a>
                            </div>
                        </div>

                        <div class="feed-comments small-12 large-6 column">
                            <comment-thread :thread="evidence.discussionThread"
                                            :create-thread-endpoint="evidence._links.createThread"
                            />
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
                    <i class="icon-advanced-list"></i>
                    <p>There is no activity yet. To get started you can <a href="#" title="Share something" @click="openShareLearningMomentModal">share something</a>.</p>
                </div>
                <button v-else-if="metadata.cursor.next && metadata.count > data.length" class="expand" @click.prevent="append(metadata.cursor.next)">
                    Load more
                </button>
            </div>
        </div>


        <!-- TODO create a Vue equivalent of $.confirmDialog -->
<!--        <reveal-modal ref="deleteModal">-->
<!--            <h2>Please confirm</h2>-->
<!--            <p>Are you sure you wish to delete this learning moment?</p>-->
<!--            <ul class="flex-list buttons">-->
<!--                <li><a @click.prevent="deleteEvidence(currentlyDeletingEvidence)" class="button submit">Yes</a></li>-->
<!--                <li><a @click.prevent="$refs.deleteModal.close()" class="button">No</a></li>-->
<!--            </ul>-->
<!--        </reveal-modal>-->

        <toast ref="toast"></toast>
    </div>

</template>
