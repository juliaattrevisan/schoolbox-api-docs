<script>

import {stringify} from 'qs';
import axios from 'axios';
import foundationMixin from '@schoolbox/foundation-mixin';
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
     * Constant values which this component may expose. Generally, this includes
     * information which a component including this component might want to know
     * about:
     * * sensible default values for this component
     * * event names
     *
     * (This is not part of Vue, and isn't a convention yet: putting it out
     *  there as an idea for discussion.)
     */
    constants: {
        EVENT_CHANGE,
    },

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [foundationMixin],

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
         * The route which shows the badge create form.
         */
        getBadgeCreateFormRoute: {
            type: String,
            default: '',
            required: true,
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
        },

        /**
         * Opens a modal window for deleting this badge.
         *
         * @param {Object} badge
         */
        openDeleteModal(badge) {
          this.currentBadge = badge;
          this.$refs.deleteModal.open();
        },

        /**
         * Closes a modal window for deleting this badge.
         */
        closeDeleteModal() {
          this.$refs.deleteModal.close();
        },

        /**
         * Perform delete
         */
        deleteBadge() {
            this.$refs.toast.activate('Deleting badge...');
            let newData = this.data.filter((v) => v.id !== this.currentBadge.id);
            axios.delete(this.currentBadge._links.delete)
                .then(() => {
                    this.data = [];
                    this.$refs.toast.timeout('Badge successfully deleted', true);
                    this.$nextTick(() => {
                        this.data = newData;
                        this.metadata.count -= 1;
                    });
                })
                .catch(() => this.$refs.toast.timeout('Failed to delete badge', false))
                .finally(() => {
                    this.closeDeleteModal()
                })
        },

        /**
         * Is the current filter set empty? This changes the empty state which
         * is displayed.
         *
         * @return {Boolean}
         */
        isSelectedFiltersEmpty() {
            let nonEmptyFilters = Object.values(this.selectedFilters).filter(function (v1) {
                if (v1 instanceof Object) {
                    // Each value in this object will be either a string 0 or 1:
                    // 1 if it is selected, 0 if it is not
                    return Object.values(v1).some(v2 => Number(v2) === 1);
                }
                return false;
            });
            return nonEmptyFilters.length > 0;
        },


        /**
         * Generate a URL which references this page with all filters cleared.
         *
         * @return {String}
         */
        generateResetFilterUrl() {
            let emptyQueryString = {};
            Object.keys(this.selectedFilters).forEach(k => emptyQueryString[k] = '');
            return "?" + new URLSearchParams(emptyQueryString).toString();
        },
    },
}
</script>

<template>
    <div>
        <div v-if="showSkeletonLoader" class="row">
            <div class="small-12 columns">
                <div v-for="n in 3" class="card badge skeleton">
                    <div class="img"></div>
                    <h3></h3>
                    <p class="meta"></p>
                </div>
            </div>
        </div>
        <div v-else>
            <div v-if="data.length === 0" class="empty-state">
                <!-- If no badges were found in the search query -->
                <template v-if="isSelectedFiltersEmpty()">
                    <i class="icon-search"></i>
                    <p>
                        No badges were found.
                        <a :href="generateResetFilterUrl()" title="Reset filters">Reset filters</a> to see all available badges.
                    </p>
                </template>

                <!-- If there are no badges in the system at all -->
                <template v-else>
                    <i class="icon-badge"></i>
                    <p>
                        <!-- the getBadgeCreateFormRoute could optionally contain a ?returnUrl=... appendage -->
                        <template v-if="getBadgeCreateFormRoute !== '' && getBadgeCreateFormRoute[0] !== '?'">
                            No badges have been created.
                            Start by <a :href="getBadgeCreateFormRoute" title="Create the first badge">creating the first badge</a>.
                        </template>
                        <template v-else>
                            There are no goals for you to choose yet. Please try again soon.
                        </template>
                    </p>
                </template>
            </div>
            <template v-else>
                <section class="content">
                    <div class="badge-grid v-card-list">
                        <div class="badge v-card" v-for="badge in data" :key="badge.id">
                            <!-- I'd apply the click to the entire <li> but that intercepts dropdown clicks -->
                            <div class="card-feature-image">
                                <a @click.prevent="openDetailModal(badge)" :title="badge.name">
                                    <badge-image :badge="badge"></badge-image>
                                </a>
                            </div>
                            <div :class="{'': !showBadgeMenus, 'actions-small-1': showBadgeMenus,'checkbox-content': !showBadgeMenus, 'card-content': true}">
                                <div class="list-item">
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
                                </div>
                                <nav v-if="showBadgeMenus">
                                  <a href="#"
                                     class="icon-overflow"
                                     :data-dropdown="'badge-' + String(badge.id) + '-dropdown'"
                                     aria-expanded="false" title="Actions"></a>
                                </nav>
                                <ul v-if="showBadgeMenus" :id="'badge-' + String(badge.id) + '-dropdown'" class="f-dropdown" data-dropdown-content>
                                    <li><a @click.prevent="openAwardAsAchievementModal(badge)" class="icon-achievement" title="Award achievement">Award achievement</a></li>
                                    <li v-if="badge.allowAsGoal"><a @click.prevent="openSetAsGoalModal(badge)" class="icon-goal" title="Set goal">Set goal</a></li>
                                    <li><a @click.prevent="openAchievementWithdrawModal(badge)" class="icon-withdraw-achievement" title="Withdraw achievement">Withdraw achievement</a></li>
                                    <li v-if="badge.allowAsGoal"><a @click.prevent="openGoalWithdrawModal(badge)" class="icon-close" title="Remove goal">Remove goal</a></li>
                                    <li><a :href="badge._links.update" class="icon-modify" title="Modify">Modify</a></li>
                                  <li><a @click.prevent="openDeleteModal(badge)" class="icon-delete">Delete</a></li>
                                </ul>
                                <p class="meta" v-if="badge.category" :title="'Category: ' + badge.category.name">{{ badge.category.name }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Load more button, if there are more to load -->
                    <a @click.prevent="append(metadata.cursor.next, true)"
                       class="button load-more expand"
                       title="Load more"
                       v-if="metadata.cursor.next && data.length < metadata.count">Load more</a>
                </section>
            </template>
        </div>

        <reveal-modal size="medium" ref="detailModal">
            <badge-detail :badge="currentBadge" v-if="currentBadge"></badge-detail>
        </reveal-modal>

        <reveal-modal size="medium" ref="awardAsAchievementModal" :closeOnBackgroundClick="false">
            <badge-award v-if="currentBadge"
                         :badge="currentBadge"
                         :endpoint="currentBadge._links.awardAsAchievement"
                         :expiry="currentBadge.achievementExpiry"
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
                <template v-slot:expiryMessage="slotProps">
                    <p class="meta" v-if="currentBadge.achievementExpiry">
                        This award will expire in {{ slotProps.expiryLabel }}, on {{ slotProps.expiryDate }}.
                    </p>
                </template>
            </badge-award>
        </reveal-modal>

        <reveal-modal size="medium" ref="setAsGoalModal" :closeOnBackgroundClick="false">
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
                    <h2>Set &lsquo;<b>{{ slotProps.badge.name }}</b>&rsquo; as a goal</h2>
                </template>
                <template v-slot:confirmButton>
                    Set goal
                </template>
            </badge-award>
        </reveal-modal>

        <reveal-modal ref="deleteModal">
            <template v-if="currentBadge">
                <h2>Delete <strong>&lsquo;{{ currentBadge.name }}&lsquo;</strong></h2>
                <div class="alert-box alert icon-alert">WARNING! Any users who have been awarded this badge will have it removed from their collection.</div>
                <p>Are you sure you wish to delete the badge '{{ currentBadge.name }}'?</p>
                <ul class="flex-list buttons">
                    <li><a @click.prevent="deleteBadge()" class="button submit">Delete</a></li>
                    <li><a @click.prevent="closeDeleteModal()" class="button">Cancel</a></li>
                </ul>
            </template>
        </reveal-modal>

        <badge-achievement-withdraw-modal ref="achievementWithdrawModal"></badge-achievement-withdraw-modal>
        <badge-goal-withdraw-modal ref="goalWithdrawModal"></badge-goal-withdraw-modal>

        <toast ref="toast"></toast>
    </div>
</template>
