<script>

import MediaArticle from '@schoolboxVue/components/media-article';
import Storage from '@schoolboxVue/components/storage';
import CommentThread from './comment-thread';
import RevealModal from "@schoolboxVue/components/reveal-modal";
import Toast from "@schoolboxVue/components/toast";
import AwardDetailModal from "../../../Badge/Award/vue/award-detail-modal";
import BadgeImage from "../../../Badge/vue/badge-image";
import foundationMixin from "@schoolboxVue/components/foundation-mixin";
import userProfileConstants from '../../../vue/user-profile/constants';
import cdnPrefixMixin from '@schoolboxVue/components/cdn-prefix-mixin';


export default {
    components: {
        AwardDetailModal,
        Toast,
        RevealModal,
        MediaArticle,
        Storage,
        CommentThread,
        BadgeImage,
    },
    mixins: [
        foundationMixin,
        cdnPrefixMixin,
    ],
    props: {
        siteVersion: {
            type: String,
            required: true
        }
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
    computed: {
        jQuery: () => window.jQuery,
        config: () => window.learningEvidenceFeedConfig,
        dateDisplayFormats: () => window.dateDisplayFormats,
        groupedEvidence() {
            return this.groupFeedResults(this.data);
        },
        currentCount () {
            return data.length;
        },
        returnUrl() {
            return btoa(window.location.pathname)
        }
    },
    mounted() {
        document.addEventListener(userProfileConstants.events.AWARDS_UPDATED, () => this.load());
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
        async append (cursor) {
            const params = {};
            if (cursor) {
                params.cursor = cursor;
            }
            try {
                const response = await fetch(this.config.routes.getFeed + '?' + new URLSearchParams(params));
                const responseData = await response.json();
                this.data = this.data.concat(responseData.data ? responseData.data : [])
                this.metadata.count = responseData.metadata?.count ? responseData.metadata.count : 0;
                this.metadata.cursor = responseData.metadata?.cursor ? responseData.metadata.cursor : null;
            } catch (error) {
                console.warn(error);
                this.loading = false;
            } finally {
                this.showSkeletonLoader = false
            }
        },

        /**
         * Groups learning evidence / badges by the date which it was posted.
         *
         * @param {Array} feedResults
         * @return Array
         */
        groupFeedResults(feedResults) {
            let groupedEvidenceByDate = {};
            feedResults.forEach(v => {
                let date = dayjs(v.date),
                    key  = date.format('YYYYMMDD');
                if (!(key in groupedEvidenceByDate)) {
                    groupedEvidenceByDate[key] = {
                        date:     dayjs(date).startOf('d'),
                        evidence: []
                    }
                }
                v.date = date;
                groupedEvidenceByDate[key].evidence.push(v);
            });

            // Flatten grouped evidence into an array
            let groupedEvidence = Object.values(groupedEvidenceByDate).sort(
                (a, b) => a.date.isBefore(b.date) ? 1 : -1
            );

            // Ensure that each evidence within the group is sorted
            groupedEvidence.forEach(v => {
                v.evidence.sort(
                    (a, b) => a.date.isBefore(b.date) ? 1 : -1
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
            let now     = dayjs().startOf('d'),
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
            return dayjs.duration(dayDiff, 'd').humanize(true);
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
        async deleteEvidence (evidence) {
            this.$refs.toast.activate('Deleting...');

            //awards, other things may also be deleted in the future, so ensure this is also the right data type
            const removeEvidenceToDelete = (data) => {
                if (data.discriminator !== evidence.discriminator) {
                    return true;
                }
                return data.object.id !== evidence.object.id
            }
            const newData = this.data.filter(removeEvidenceToDelete);

            // Replacing the data in place in the same tick causes issues with
            // the MediaElement player and Foundation dropdowns for some reason.
            // Clear it first; then replace it with the filtered data in the
            // next tick.
            try {
                await fetch(evidence.object._links.delete, {
                    method: 'DELETE'
                });
                this.data = [];
                this.$refs.toast.timeout('Deleted', true);
                this.$nextTick(() => {
                    this.data = newData;
                    this.metadata.count -= 1;
                });
            } catch (error) {
                this.$refs.toast.timeout('Could not delete', false)
            } finally {
                this.$refs.deleteModal.close();
            }
        },


        /**
         * Open the view badge award modal.
         *
         * @param {Object} badgeAward The badge award object to display
         */
        openViewBadgeAwardModal(badgeAward) {
            this.$refs.awardDetailModal.open(badgeAward);
        },

        /**
         * Format the date as specified
         *
         * @param { Date } 
         * @param { String } 
         * 
         * @returns { String }
         */
        formatDate (date, format) {
            return dayjs(date).format(format);
        },

    },

}
</script>

<template>
    <div>
        <div class="feed" v-for="(evidenceGroup, index) in groupedEvidence" :key="index">
            <div class="feed-column left"></div>
            <section class="activity-feed">
              <div v-if="index === 0" class="profile-learning-actions">
                <div>
                  <template v-if="config.routes.getCreateEvidenceForm">
                  <a href="#" title="Share Learning" class="learning icon-share" data-reveal-id="shareLearningMoment"><span>Share Learning</span></a>
                  </template>
                  <template v-if="config.routes.getSetAsGoalForm">
                    <a :href="config.routes.getSetAsGoalForm + '?' + config.returnUrl.name + '=' + config.returnUrl.current"
                       title="Set goals" class="goals icon-goal"><span>Set Goals</span></a>
                  </template>
                  <template v-if="config.routes.getSetAsGoalForSelfForm">
                    <a :href="config.routes.getSetAsGoalForSelfForm + '?' + config.returnUrl.name + '=' + config.returnUrl.current"
                       title="Set goals" class="goals icon-goal"><span>Set My Goals</span></a>
                  </template>
                  <template v-if="config.routes.getAwardAsAchievementForm">
                    <a :href="config.routes.getAwardAsAchievementForm + '?' + config.returnUrl.name + '=' + config.returnUrl.current"
                       title="Award achievement" class="achievement icon-achievement"><span>Award Achievement</span></a>
                  </template>
                </div>
              </div>
                <h2 class="subheader no-margin">
                    <time :datetime="formatDate(evidenceGroup.date, 'YYYY-MM-DD')"
                          :title="formatDate(evidenceGroup.date, dateDisplayFormats.dayjsDateLong)"
                    >{{ renderCreatedAt(evidenceGroup.date) }}</time>
                </h2>
                <ul class="information-list">
                    <li v-for="(evidence, index) in evidenceGroup.evidence" class="feed-item" :key="index">
                        <template v-if="evidence.discriminator === 'evidence'">
                            <!-- This evidence isn't necessarily editable -->
                            <div :class="{'actions-small-1': hasMenu(evidence.object), 'actions-small-0': !hasMenu(evidence.object)}">
                                <div class="card small-12 compact">
                                    <a :href="evidence.object.creator._links.profile" title="View Profile">
                                        <img :src="evidence.object.creator._links.avatar" alt="View Profile">
                                        <p>{{ evidence.object.creator.fullName }}</p>
                                        <time class="pipe meta"
                                              :datetime="formatDate(evidence.object.createdAt)"
                                              :title="formatDate(evidence.object.createdAt, dateDisplayFormats.dayjsDatetime)"
                                        >{{ formatDate(evidence.object.createdAt, dateDisplayFormats.dayjsTime) }}</time>
                                    </a>
                                </div>
                                <nav v-if="hasMenu(evidence.object)">
                                    <a href="#"
                                       class="icon-overflow"
                                       :data-dropdown="'evidence-' + String(evidence.object.id) + '-dropdown'"
                                       aria-expanded="false" title="Actions"></a>
                                </nav>
                                <ul v-if="hasMenu(evidence.object)" :id="'evidence-' + String(evidence.object.id) + '-dropdown'" class="f-dropdown" data-dropdown-content>
                                    <li><a v-if="evidence.object._links.modifyForm" :href="evidence.object._links.modifyForm + '?returnUrl=' + returnUrl " class="icon-modify" title="Modify">Modify</a></li>
                                    <li><a @click.prevent="showDeleteModal(evidence)" v-if="evidence.object._links.delete" class="icon-delete" title="Delete">Delete</a></li>
                                </ul>
                            </div>

                            <div class="feed-body small-12 columns">
                                <!-- Is there an attachment? Display it appropriately -->
                                <article v-if="evidence.object.filename && evidence.object.storage">
                                    <storage :storage="evidence.object.storage" :filename="evidence.object.filename"></storage>
                                </article>

                                <!-- Display evidence body, with media players initialised -->
                                <media-article v-if="evidence.object.body">
                                    <div v-html="evidence.object.body" />
                                </media-article>

                                <!-- Display curriculum codes and tags -->
                                <div class="tags">
                                  <p class="meta" v-if="evidence.object.curriculumNodes.length > 0 || evidence.object.tags.length > 0">
                                      Tags:
                                      <template v-for="(curriculumNode, index) in evidence.object.curriculumNodes" :key="`${index}-${curriculumNode.code}`"> 
                                          <template v-if="index !== 0">, </template>
                                          <span :title="curriculumNode.name">{{ curriculumNode.code }}</span>
                                      </template>
                                      <template v-if="evidence.object.curriculumNodes.length > 0 && evidence.object.tags.length > 0">, </template>
                                      <template v-for="(tag, index) in evidence.object.tags" :key="`${index}-${tag.name}`">
                                          <template v-if="index !== 0">, </template><span>{{ tag.name }}</span>
                                      </template>
                                  </p>
                                </div>
                            </div>

                            <div class="feed-comments small-12 columns">
                                <comment-thread 
                                    :thread="evidence.object.discussionThread"
                                    :create-thread-endpoint="evidence.object._links.createThread"
                                    :can-comment="config.canComment"
                                    :cdn-prefix="cdnPrefix"
                                    :site-version="siteVersion"
                                />
                            </div>
                        </template>
                        <template v-if="evidence.discriminator === 'badgeAward'">
                            <div class="card badge-card small-12">
                                <a href="#" @click.prevent="openViewBadgeAwardModal(evidence.object)">
                                    <badge-image :badge="evidence.object.badge" :css=" evidence.object.status === 'goal' ? 'goal' : ''"></badge-image>
                                    <h3>{{ evidence.object.badge.name }}</h3>
                                    <p class="meta">
                                        <template v-if="evidence.object.status === 'achievement'">Achievement awarded</template>
                                        <template v-if="evidence.object.status === 'goal'">Goal set</template>
                                        by {{ evidence.object.awardedBy.fullName }}
                                        |
                                        <time class="meta" :datetime="formatDate(evidence.object.awardedAt)"
                                              :title="formatDate(evidence.object.awardedAt, dateDisplayFormats.dayjsDatetime)">
                                        {{ formatDate(evidence.object.awardedAt, dateDisplayFormats.dayjsTime) }}
                                        </time>
                                    </p>
                                </a>
                            </div>

                        </template>
                    </li>
                </ul>
            </section>
            <div class="feed-column right"></div>
        </div>
        <div class="skeleton-feed" v-if="showSkeletonLoader">
            <ul class="information-list">
                <li class="row" v-for="n in 3" :key="n">
                    <div class="small-12 card skeleton">
                        <div class="img"></div>
                        <h3></h3>
                        <p class="meta"></p>
                    </div>
                </li>
            </ul>
        </div>
        <div v-else>
            <div v-if="metadata.count === 0" class="empty-state">
                <i class="icon-advanced-list"></i>

                <template v-if="config.currentUser.id === config.student.id">
                    <!-- Current user -->
                    <p>You don't have any activity yet.</p>

                    <!-- Can share moments? -->
                    <p v-if="config.routes.getCreateEvidenceForm || config.routes.getSetAsGoalForSelfForm">
                        <template v-if="config.routes.getCreateEvidenceForm">
                            <!--
                                All these tags need to be on the same line,
                                because otherwise there will be a space between
                                the link and the comma -->
                            <a href="#" title="Share a learning moment" data-reveal-id="shareLearningMoment">Share your learning</a><template v-if="config.routes.getSetAsGoalForm && config.routes.getAwardAsAchievementForm">, </template><template v-else-if="config.routes.getSetAsGoalForSelfForm"> or </template>
                        </template>

                        <template v-if="config.routes.getSetAsGoalForSelfForm">
                            <a :href="config.routes.getSetAsGoalForSelfForm + '?' + config.returnUrl.name + '=' + config.returnUrl.current"
                               title="Set a goal">
                                <!-- Different capitalization for if this is start or middle of a sentence -->
                                <template v-if="config.routes.getCreateEvidenceForm">set a goal</template>
                                <template v-else>Set a goal</template>
                            </a>
                        </template>

                        to see it appear in your activity feed.
                    </p>
                </template>

                <template v-else>
                    <!-- Parent or student -->
                    <p>{{ config.student.firstname }} doesn't have any activity yet.</p>

                    <!-- What can the current user do? -->

                    <!-- Can share moments? -->
                    <p v-if="config.routes.getCreateEvidenceForm || config.routes.getSetAsGoalForm || config.routes.getAwardAsAchievementForm">
                        <template v-if="config.routes.getCreateEvidenceForm">
                            <!--
                                All these tags need to be on the same line,
                                because otherwise there will be a space between
                                the link and the comma -->
                            <a href="#" title="Share a learning moment" data-reveal-id="shareLearningMoment">Share a learning moment</a><template v-if="config.routes.getSetAsGoalForm && config.routes.getAwardAsAchievementForm">, </template><template v-else-if="config.routes.getSetAsGoalForm || config.routes.getAwardAsAchievementForm"> or </template>
                        </template>

                        <template v-if="config.routes.getSetAsGoalForm">
                            <a :href="config.routes.getSetAsGoalForm + '?' + config.returnUrl.name + '=' + config.returnUrl.current"
                               title="Set a goal">
                                <!-- Different capitalization for if this is start or middle of a sentence -->
                                <template v-if="config.routes.getCreateEvidenceForm">set a goal</template>
                                <template v-else>Set a goal</template>
                            </a><template v-if="config.routes.getAwardAsAchievementForm"> or </template>
                        </template>

                        <template v-if="config.routes.getAwardAsAchievementForm">
                            <a :href="config.routes.getAwardAsAchievementForm + '?' + config.returnUrl.name + '=' + config.returnUrl.current"
                               title="Award an achievement">
                                <template v-if="config.routes.getCreateEvidenceForm || config.routes.getSetAsGoalForm">award an achievement</template>
                                <template v-else>Award an achievement</template>
                            </a>
                        </template>

                        to see it appear here.
                    </p>

                    <!-- Can't share moments? -->
                    <p v-else>
                        When {{ config.student.firstname }} or their teacher shares learning, it will appear here.
                    </p>

                </template>
            </div>

            <div class="feed-load-more content" v-else-if="metadata.cursor.next && metadata.count > data.length">
                <button class="load-more expand" @click.prevent="append(metadata.cursor.next)" title="Load more">
                    Load more
                </button>
            </div>
        </div>

        <!-- TODO create a Vue equivalent of $.confirmDialog -->
        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
            <p>Are you sure you wish to delete this Learning Moment?</p>
            <ul class="flex-list buttons">
                <li><a @click.prevent="deleteEvidence(currentlyDeletingEvidence)" id="confirm-delete-learning-moment" class="button submit">Delete</a></li>
                <li><a @click.prevent="$refs.deleteModal.close()" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>

        <award-detail-modal ref="awardDetailModal" :cdn-prefix="this.cdnPrefix" :site-version="this.siteVersion"></award-detail-modal>

        <toast ref="toast"></toast>
    </div>

</template>
