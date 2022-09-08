<script>
import AwardDetailModal from './award-detail-modal';
import AchievementWithdrawModal from "./award-achievement-withdraw-modal";
import BadgeImage from './../../vue/badge-image';
import GoalWithdrawModal from "./award-goal-withdraw-modal";
import foundationMixin from '@schoolboxVue/components/foundation-mixin';
import goalMixin from './goal-mixin';
import RelativeTime from '@schoolboxLearningMomentEvidence/relative-time';
import toastMixin from '@schoolboxVue/components/toast-mixin';
import userProfileConstants from '../../../vue/user-profile/constants';
import userProfileMixin from '../../../vue/user-profile/mixin';
import cdnPrefixMixin from '@schoolboxVue/components/cdn-prefix-mixin';

export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "goal-feed",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [
        cdnPrefixMixin,
        foundationMixin,
        goalMixin,
        toastMixin,
        userProfileMixin,
    ],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: {
        siteVersion: String
    },

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        AwardDetailModal,
        AchievementWithdrawModal,
        BadgeImage,
        GoalWithdrawModal,
        RelativeTime
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            data: [],
            loading: true,
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
        document.addEventListener(userProfileConstants.events.AWARDS_UPDATED, () => this.updateAwards());
        this.updateAwards();
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
        config: () => window.learningBadgeGoalFeedConfig
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {
        /**
         * Open a modal to show the badge that was given as an award
         *
         * @param award
         */
        openDetailModal(award) {
            this.$refs.awardDetailModal.open(award);
        },

        /**
         * Convert a goal into an achievement
         *
         * @param award
         */
        async awardAchievement(award) {
            try {
                await fetch(award._links.goalAward, {
                    method: 'POST',
                    body: JSON.stringify({ users: [award.recipient] })
                });
                this.toastSuccess('Achievement awarded');
                /* Temporarily replaced implementation of self.updateAwards()
                 * goal-feed.vue can't tell achievement-feed.vue to update itself; they don't
                 * share a common parent. They're entirely separate vue apps. An action inside
                 * goal-feed affects the data in achievement-feed, but I can't talk to
                 * achievement-feed from goal-feed.
                 *
                 * The forced reload will cause achievement-feed to seem updated, for now
                 */
                window.location.reload();
            } catch (error) {
                console.warn(error);
                this.toastError('Failed to award achievement');
            }
        },

        /**
         * Open a confirmDialog for withdrawing a badge
         *
         * @param award
         */
        openAchievementWithdrawModal(award) {
            this.$refs.achievementWithdrawModal.open(award);
        },

        /**
         * Open a confirmDialog for withdrawing a badge
         *
         * @param award
         */
        openGoalWithdrawModal(award) {
            this.$refs.goalWithdrawModal.open(award);
        },

        /**
         * Generate an identifier for tots
         *
         * @param award
         * @returns {string}
         */
        generateIdentifier(award) {
            return 'header-options-' + award.badge.category.id + '-' + award.badge.id + '-' + award.status;
        },

        /**
         * Fetch the list of awards
         */
        async updateAwards() {
            /* Access the feed endpoint, grab the awards for the user
             */
            try {
                const response = await fetch(this.config.routes.getAwardFeed);
                this.data = await response.json();
            } catch (error) {
                console.warn(error);
            } finally {
                this.loading = false;
            }
        },

        /**
         * Decide if this Award has a tot menu
         * If there aren't any actions you can undertake, hide the tot
         */
        hasTot(award) {
            return this.canWithdraw(award);
        },

        /**
         * Decide if the current user can Withdraw an Achievement or Goal
         *
         * @param award
         * @returns {boolean}
         */
        canWithdraw(award) {
            return Boolean(award._links.goalWithdraw) || Boolean(award._links.achievementWithdraw);
        }
    }
}
</script>
<template>
    <div>
        <template v-if="this.loading">
            <dl class="tabs">
                <dd class="skeleton" v-for="n in 3">
                    <a></a>
                </dd>
            </dl>
        </template>
        <template v-else-if="this.data.categories">
            <section class="awards">
                <template v-for="category in this.data.categories">
                    <div class="content">
                        <h2 class="subheader" :title="'Category: ' + category.name">{{ category.name }}</h2>

                        <div class="badge-grid v-card-list">
                            <div class="badge v-card" v-for="award in category.awards">
                                <div class="card-feature-image">
                                    <a href="#" @click.prevent="openDetailModal(award)" :title="award.badge.name">
                                        <badge-image :badge="award.badge" :css="getCss(award)"></badge-image>
                                    </a>
                                    <template v-if="award.count > 1">
                                        <div class="award-count" :title="'Awarded ' + award.count + ' times'">x{{ award.count }}</div>
                                    </template>
                                </div>
                                <div class="actions-small-1 card-content">
                                    <div class="list-item">
                                        <h3><a href="#" @click.prevent="openDetailModal(award)" :title="award.badge.name">{{ award.badge.name }}</a></h3>
                                    </div>
                                    <template v-if="hasTot(award)">
                                    <nav>
                                      <a class="icon-more" title="Actions" :data-dropdown="generateIdentifier(award)"></a>
                                    </nav>
                                    <ul :id="generateIdentifier(award)" class="f-dropdown" data-dropdown-content>

                                      <!-- Only show Withdraw button if you can actually do it
                                        -- Students can't withdraw their own Achievement
                                        -->
                                      <template v-if="canWithdraw(award)">
                                        <template v-if="!award.isGoal">
                                          <li><a @click.prevent="openAchievementWithdrawModal(award)" class="icon-withdraw-achievement" title="Withdraw achievement">Withdraw achievement</a></li>
                                        </template>
                                        <template v-else>
                                          <li><a @click.prevent="awardAchievement(award)" class="icon-showcase" title="Award achievement">Award achievement</a></li>
                                          <li><a @click.prevent="openGoalWithdrawModal(award)" class="icon-close" title="Remove goal">Remove goal</a></li>
                                        </template>
                                      </template>
                                    </ul>
                                    </template>
                                    <p class="meta">
                                        <!-- Change metadata text subtly depending on the Award status
                                          -- Goals and Achievements, while badges, aren't the same thing
                                          -->
                                        <template v-if="award.isGoal">
                                          Goal set
                                        </template>
                                        <template v-else>
                                          Awarded
                                        </template>
                                        <relative-time :datetime="award.awardedAt"></relative-time>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </template>
        <template v-else>
            <div class="empty-state">
                <i class="icon-goal"></i>

                <template v-if="config.currentUser.id === config.student.id">
                    <!-- Current user -->
                    <p>You don't have any goals yet.</p>
                    <p v-if="config.routes.getSetAsGoalForSelfForm">
                        <a :href="config.routes.getSetAsGoalForSelfForm + '?' + config.returnUrl.name + '=' + config.returnUrl.current" title="Set a goal">Set a goal</a> to see it appear here.
                    </p>
                </template>
                <template v-else>
                    <!-- Parent or student -->
                    <p>{{ config.student.givenName }} doesn't have any goals yet.</p>
                    <p v-if="config.routes.getSetAsGoalForm">
                        <a :href="config.routes.getSetAsGoalForm + '?' + config.returnUrl.name + '=' + config.returnUrl.current" title="Set a goal">Set a goal</a> for {{ config.student.givenName }}.
                    </p>
                </template>
            </div>
        </template>

        <award-detail-modal ref="awardDetailModal" :cdn-prefix="this.cdnPrefix" :site-version="this.siteVersion"></award-detail-modal>

        <achievement-withdraw-modal ref="achievementWithdrawModal" v-on:withdraw-complete="emitUpdateToAllUserProfileFeeds"></achievement-withdraw-modal>
        <goal-withdraw-modal ref="goalWithdrawModal" v-on:withdraw-complete="emitUpdateToAllUserProfileFeeds"></goal-withdraw-modal>
    </div>
</template>
