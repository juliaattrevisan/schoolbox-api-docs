<script>

import axios from 'axios';
import Toast from "@schoolbox/toast";
import RevealModal from "@schoolbox/reveal-modal";
import BadgeAward from "./badgeAward";
import BadgeDetail from "./badgeDetail";
import BadgeWithdrawAchievementModal from "./badge-withdraw-achievement-modal";
import BadgeWithdrawGoalModal from "./badge-withdraw-goal-modal";

export default {
    components: {
      BadgeAward,
      BadgeDetail,
      BadgeWithdrawAchievementModal,
      BadgeWithdrawGoalModal,
      RevealModal,
      Toast
    },
    props: {
        /**
         * The route via which badges are retrieved.
         */
        getBadgesRoute: {
            type: String,
            default: '',
        },

        /**
         * The route via which new badges are posted.
         *
         * TODO remove as this seems unused
         */
        postBadgeRoute: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            currentBadge: null,
            data: [],
            search: {},
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
        }
    },
    computed: {
        jQuery: () => window.jQuery,
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
         * Load badges.
         */
        load() {
            this.data = [];
            this.append(null);
        },

        /**
         * Appends badges to the data list.
         *
         * @param {String|null} cursor
         */
        append(cursor) {
            // TODO this is very generic, a candidate to be factored into its own place
            let params = {};
            if (cursor) {
                params.cursor = cursor;
            }
            this.$refs.toast.activate('Loading...');
            axios
                .get(this.getBadgesRoute, {params})
                .then(response => {
                    this.data            = this.data.concat(response?.data?.data ? response.data.data : [])
                    this.metadata.count  = response?.data?.metadata?.count  ? response.data.metadata.count  : 0;
                    this.metadata.cursor = response?.data?.metadata?.cursor ? response.data.metadata.cursor : null;
                    this.$refs.toast.timeout('Loaded', true);
                })
                .catch(() => this.$refs.toast.timeout('Loading failed', false))
                .finally(() => this.showSkeletonLoader = false);
        },

        /**
         * Opens a modal window for viewing this badge's full details.
         *
         * @param {Object} badge
         */
        openDetailModal(badge) {
            this.currentBadge = badge;
            this.$refs.detailModal.open();
        },

        /**
         * Open the modal which lets you withdraw an achievement
         *
         * @param {Object} badge
         */
        openWithdrawAchievementModal(badge) {
            this.$refs.withdrawAchievementModal.open(badge);
        },

        /**
         * Open the modal which lets you withdraw a goal
         *
         * @param {Object} badge
         */
        openWithdrawGoalModal(badge) {
          this.$refs.withdrawGoalModal.open(badge);
        },

        /**
         * Opens a modal window for viewing this badge's award form.
         *
         * @param {Object} badge
         */
        openAwardAsAchievementModal(badge) {
            this.currentBadge = badge;
            this.$refs.awardAsAchievementModal.open();
        },

        /**
         * Closes the modal window for the award form.
         */
        closeAwardAsAchievementModal() {
            this.$refs.awardAsAchievementModal.close();
        },

        /**
         * Opens a modal window for viewing this badge's set as goal form.
         *
         * @param {Object} badge
         */
        openSetAsGoalModal(badge) {
            this.currentBadge = badge;
            this.$refs.setAsGoalModal.open();
        },

        /**
         * Closes the modal window for the set as goal form.
         *
         * @param {Object} badge
         */
        closeSetAsGoalModal(badge) {
            this.$refs.setAsGoalModal.close();
        },
    },
}
</script>

<template>
    <div>
        <section v-if="showSkeletonLoader">
            <div v-for="n in 3" class="list-item">
                <div class="small-12 card skeleton">
                    <div class="img"></div>
                    <h3></h3>
                    <p class="meta"></p>
                </div>
            </div>
        </section>
        <section v-else class="content">
            <div v-if="data.length === 0" class="empty-state">
                <i class="icon-schoolbox"></i>
                <p>No badges have been created yet.</p>
            </div>
            <template v-else>

                <!-- Search panel. Should this use the filters system? -->
                <ul class="flex-list buttons">
                    <li><input type="text" placeholder="Badge Name" v-model="search.badgeName" /></li>
                    <li><input type="text" placeholder="Creator" v-model="search.creator" /></li>
                    <li><input type="text" placeholder="Category" v-model="search.category" /></li>
                    <li><a href="#">Reset to defaults</a></li>
                    <li><a href="#" class="submit">Apply filters</a></li>
                </ul>

                <ul class="grid small-block-grid-2 medium-block-grid-3 large-block-grid-4">
                    <li v-for="badge in data" :key="badge.id">
                        <!-- I'd apply the click to the entire <li> but that intercepts dropdown clicks -->
                        <a @click.prevent="openDetailModal(badge)">
                            <img :src="badge.image._links.image" :alt="badge.name" />
                        </a>
                        <div class="actions-small-1">
                            <div class="compact card list-item" @click.prevent="openDetailModal(badge)">
                                <h3><a>{{ badge.name }}</a></h3>
                                <p class="meta" v-if="badge.category">{{ badge.category.name }}</p>
                            </div>
                            <nav>
                                <a href="#"
                                   class="icon-overflow"
                                   :data-dropdown="'badge-' + String(badge.id) + '-dropdown'"
                                   aria-expanded="false" title="Actions"></a>
                            </nav>
                            <ul :id="'badge-' + String(badge.id) + '-dropdown'" class="f-dropdown" data-dropdown-content>
                                <li><a @click.prevent="openAwardAsAchievementModal(badge)" class="icon-showcase" title="Award">Award achievement</a></li>
                                <li><a @click.prevent="openSetAsGoalModal(badge)" class="icon-goal" title="Set as Goal">Set goal</a></li>
                                <li><a @click.prevent="openWithdrawAchievementModal(badge)" class="icon-close" title="Withdraw awards" >Withdraw achievement</a></li>
                                <li><a @click.prevent="openWithdrawGoalModal(badge)" class="icon-close" title="Unset goal" >Unset goal</a></li>
                                <li><a href="#" class="icon-modify" title="Modify">Modify</a></li>
                                <li><a href="#" class="icon-delete" title="Delete">Delete</a></li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </template>

            <!-- Load more button, if there are more to load -->
            <a @click.prevent="append(metadata.cursor.next)"
               class="button expand"
               v-if="metadata.cursor.next && data.length < metadata.count">Load more</a>
        </section>

        <reveal-modal ref="detailModal">
            <badge-detail :badge="currentBadge" v-if="currentBadge"></badge-detail>
        </reveal-modal>

        <reveal-modal size="large" ref="awardAsAchievementModal" :closeOnBackgroundClick="false">
            <badge-award v-if="currentBadge"
                         :badge="currentBadge"
                         :endpoint="currentBadge._links.awardAsAchievement"
                         @cancel="closeAwardAsAchievementModal"
                         @done="closeAwardAsAchievementModal"
                         placeholder="Search for users to award achievement to"
                         toast-activate-message="Awarding achievement..."
                         toast-success-message="Awarded achievement"
                         toast-failure-message="Awarding achievement failed"
            >
                <template v-slot:header="slotProps">
                    <h2>Award &lsquo;<i>{{ slotProps.badge.name }}</i>&rsquo; as an Achievement</h2>
                </template>
                <template v-slot:confirmButton>
                    Award Achievement
                </template>
            </badge-award>
        </reveal-modal>

        <reveal-modal size="large" ref="setAsGoalModal" :closeOnBackgroundClick="false">
            <badge-award v-if="currentBadge"
                         :badge="currentBadge"
                         :endpoint="currentBadge._links.setAsGoal"
                         @cancel="closeSetAsGoalModal"
                         @done="closeSetAsGoalModal"
                         placeholder="Search for users to set a goal for"
                         toast-activate-message="Setting goal..."
                         toast-success-message="Goal set"
                         toast-failure-message="Setting goal failed"
            >
                <template v-slot:header="slotProps">
                    <h2>Set &lsquo;<i>{{ slotProps.badge.name }}</i>&rsquo; as a Goal</h2>
                </template>
                <template v-slot:confirmButton>
                    Set Goal
                </template>
            </badge-award>
        </reveal-modal>

        <badge-withdraw-achievement-modal ref="withdrawAchievementModal"></badge-withdraw-achievement-modal>
        <badge-withdraw-goal-modal ref="withdrawGoalModal"></badge-withdraw-goal-modal>

        <toast ref="toast"></toast>
    </div>
</template>
