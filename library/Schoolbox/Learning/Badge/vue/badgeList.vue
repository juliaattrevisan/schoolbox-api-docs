<script>

import {stringify} from 'qs';
import axios from 'axios';
import Toast from "@schoolbox/toast";
import RevealModal from "@schoolbox/reveal-modal";
import BadgeAward from "../Award/vue/badgeAward";
import BadgeDetail from "./badgeDetail";
import BadgeAchievementWithdrawModal from "../Award/vue/badge-achievement-withdraw-modal";
import BadgeGoalWithdrawModal from "../Award/vue/badge-goal-withdraw-modal";
import BadgeImage from './badge-image';

const EVENT_CHANGE = 'change';

// Foundation mixin is not required:
// the filter panel calls `jQuery.fn.foundation` with the entire document as its
// context.
// Attempting to apply Foundation again causes the dropdown menus to not work.

/**
 * @emits change The list of selected badges has changed
 */
export default {

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [],

    components: {
        BadgeAward,
        BadgeDetail,
        BadgeAchievementWithdrawModal,
        BadgeGoalWithdrawModal,
        BadgeImage,
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
            required: true,
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

        /**
         * Is the menu shown for each badge?
         */
        showBadgeMenus: {
            type: Boolean,
            default: true,
        },

        /**
         * Can badges be selected?
         */
        allowSelection: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            currentBadge: null,
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
            selectedFilters: null,
        }
    },
    methods: {
        /**
         * Load badges.
         *
         * @param {Boolean} showToast Should the toast be activated on load of
         *                            badges?
         *                            The filter calls `onFilterChange` when its
         *                            selections change, and that has its own
         *                            toast so don't show another.
         */
        load(showToast) {
            this.data = [];
            this.append(null, showToast);
        },

        /**
         * Appends badges to the data list.
         *
         * @param {String|null} cursor
         * @param {Boolean}     showToast
         */
        append(cursor, showToast) {
            // TODO this is very generic, a candidate to be factored into its own place
            let params = {};
            if (cursor) {
                params.cursor = cursor;
            }
            if (this.selectedFilters) {
                params.filters = this.selectedFilters;
            }
            if (showToast) {
                this.$refs.toast.activate('Loading...');
            }
            let config = {
                params:           params,
                paramsSerializer: params => stringify(params, {arrayFormat: 'brackets'}),
            }
            axios
                .get(this.getBadgesRoute, config)
                .then(response => {
                    this.data            = this.data.concat(response?.data?.data ? response.data.data : [])
                    this.metadata.count  = response?.data?.metadata?.count  ? response.data.metadata.count  : 0;
                    this.metadata.cursor = response?.data?.metadata?.cursor ? response.data.metadata.cursor : null;
                    if (showToast) {
                        this.$refs.toast.timeout('Loaded', true);
                    }
                })
                .catch(() => {
                    if (showToast) {
                        this.$refs.toast.timeout('Loading failed', false);
                    }
                })
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
        openAchievementWithdrawModal(badge) {
            this.$refs.achievementWithdrawModal.open(badge);
        },

        /**
         * Open the modal which lets you withdraw a goal
         *
         * @param {Object} badge
         */
        openGoalWithdrawModal(badge) {
          this.$refs.goalWithdrawModal.open(badge);
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

        /**
         * Callback for when the filters for this badge list are updated.
         *
         * Fired from outer space (i.e. from the outside window).
         *
         * @param {object} selectedFilters The filters that were selected
         */
        onFilterChange(selectedFilters) {
            this.selectedFilters    = selectedFilters;
            this.showSkeletonLoader = true;
            this.load(false);
            this.emitChange();
        },

        /**
         * Which badges (if any) are selected?
         *
         * @return {Array}
         */
        selectedBadges() {
            return this.data.filter(v => v.selected === true);
        },

        /**
         * Helper for emitting the change event: this happens when selecting a
         * badge, or reloading the filter.
         */
        emitChange() {
            this.$emit(EVENT_CHANGE);
        }
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
                <div class="badge-grid v-card-list">
                    <div class="badge v-card" v-for="badge in data" :key="badge.id">
                        <!-- I'd apply the click to the entire <li> but that intercepts dropdown clicks -->
                        <div class="card-feature-image">
                            <a @click.prevent="openDetailModal(badge)">
                                <badge-image :badge="badge"></badge-image>
                            </a>
                        </div>
                        <div class="card-content">
                            <template v-if="allowSelection">
                                <input type="checkbox"
                                       :id="'selectBadge-' + String(badge.id)"
                                       v-model="badge.selected"
                                       @change="emitChange"
                                >
                                <label :for="'selectBadge-' + String(badge.id)" class="checkbox-label">
                                    {{ badge.name }}
                                </label>
                            </template>
                            <h3 v-else>
                                <a @click.prevent="openDetailModal(badge)" :title="badge.name">{{ badge.name }}</a>
                            </h3>
                            <div :class="{'actions-small-0': !showBadgeMenus, 'actions-small-1': showBadgeMenus,}">
                                <div class="list-item">
                                    <p class="meta" v-if="badge.category" :title="'Category: ' + badge.category.name">{{ badge.category.name }}</p>
                                </div>
                                <nav v-if="showBadgeMenus">
                                  <a href="#"
                                     class="icon-overflow"
                                     :data-dropdown="'badge-' + String(badge.id) + '-dropdown'"
                                     aria-expanded="false" title="Actions"></a>
                                </nav>
                                <ul v-if="showBadgeMenus" :id="'badge-' + String(badge.id) + '-dropdown'" class="f-dropdown" data-dropdown-content>
                                  <li><a @click.prevent="openAwardAsAchievementModal(badge)" class="icon-showcase">Award achievement</a></li>
                                  <li><a @click.prevent="openSetAsGoalModal(badge)" class="icon-goal">Set goal</a></li>
                                  <li><a @click.prevent="openAchievementWithdrawModal(badge)" class="icon-close">Withdraw achievement</a></li>
                                  <li><a @click.prevent="openGoalWithdrawModal(badge)" class="icon-undo">Remove goal</a></li>
                                  <li><a :href="badge._links.update" class="icon-modify">Modify</a></li>
                                  <li><a href="#" class="icon-delete">Delete</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Load more button, if there are more to load -->
            <a @click.prevent="append(metadata.cursor.next, true)"
               class="button expand"
               v-if="metadata.cursor.next && data.length < metadata.count">Load more</a>
        </section>

        <reveal-modal ref="detailModal">
            <badge-detail :badge="currentBadge" v-if="currentBadge"></badge-detail>
        </reveal-modal>

        <reveal-modal size="small" ref="awardAsAchievementModal" :closeOnBackgroundClick="false">
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
                    <h2>Award &lsquo;<b>{{ slotProps.badge.name }}</b>&rsquo; as an Achievement</h2>
                </template>
                <template v-slot:confirmButton>
                    Award Achievement
                </template>
            </badge-award>
        </reveal-modal>

        <reveal-modal size="small" ref="setAsGoalModal" :closeOnBackgroundClick="false">
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
                    <h2>Set &lsquo;<b>{{ slotProps.badge.name }}</b>&rsquo; as a Goal</h2>
                </template>
                <template v-slot:confirmButton>
                    Set Goal
                </template>
            </badge-award>
        </reveal-modal>

        <badge-achievement-withdraw-modal ref="achievementWithdrawModal"></badge-achievement-withdraw-modal>
        <badge-goal-withdraw-modal ref="goalWithdrawModal"></badge-goal-withdraw-modal>

        <toast ref="toast"></toast>
    </div>
</template>
