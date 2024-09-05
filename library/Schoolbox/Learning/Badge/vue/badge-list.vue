<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import Toast from "@schoolboxVue/components/toast";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import BadgeAward from "../Award/vue/badgeAward";
import BadgeDetail from "./badgeDetail";
import BadgeAchievementWithdrawModal from "../Award/vue/badge-achievement-withdraw-modal";
import BadgeGoalWithdrawModal from "../Award/vue/badge-goal-withdraw-modal";
import BadgeImage from "./badge-image";
import BadgeModal from "./badge-modal";
import RelativeTime from "@schoolboxLearningMomentEvidence/relative-time";
import mixinFilter from "@schoolboxVue/mixins/filter";
import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";

export default {
    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [cdnPrefixMixin, mixinFilter],

    components: {
        RelativeTime,
        BadgeAward,
        BadgeDetail,
        BadgeAchievementWithdrawModal,
        BadgeGoalWithdrawModal,
        BadgeImage,
        RevealModal,
        Toast,
        BadgeModal,
    },

    props: {
        getBadgesRoute: {
            //the route via which badges are retrieved.
            type: String,
            default: "",
            required: true,
        },
        getBadgeCreateFormRoute: {
            //The route which shows the badge create form.
            type: String,
            default: "",
            required: true,
        },
        showBadgeMenus: {
            //Is the menu shown for each badge?
            type: Boolean,
            default: true,
        },
        allowSelection: {
            //Can badges be selected?
            type: Boolean,
            default: false,
        },
        expiryDateFormat: {
            //The Moment date format which is used to render the date when an awarding of a badge will expire. If not provided, the date will not be rendered.
            type: String,
            default: null,
        },
        selectedBadges: {
            //to know which badges are currently selected
            type: Array,
            default() {
                return [];
            },
        },
        siteVersion: String,
    },

    data() {
        return {
            currentBadge: null,
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            isModalOpen: false,
            showSkeletonLoader: true,
            selectedFilters: null,
            isDetailModalOpen: false, //whether the modal to display the badge details is open
        };
    },

    created() {
        this.filter_endpoint = this.getBadgesRoute; //assign endpoint for the mixinFilter
    },

    mounted() {
        document.addEventListener("badge-list__onFilterChange", this.onFilterChange);
    },

    methods: {
        /**
         * Opens a modal window for viewing this badge's full details.
         *
         * @param {Object} badge
         */
        openDetailModal(badge) {
            this.currentBadge = badge;
            this.isDetailModalOpen = true;
        },

        /**
         * the detail modal is closed, so we should set the boolean to
         * false so we can open it again
         */
        onDetailModalClosed() {
            this.isDetailModalOpen = false;
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
            this.isModalOpen = true;
            this.currentBadge = badge;
            this.$refs.awardAsAchievementModal.open();
        },

        /**
         * Closes the modal window for the award form.
         */
        closeAwardAsAchievementModal() {
            this.isModalOpen = false;
            this.$refs.awardAsAchievementModal.close();
        },

        /**
         * Opens a modal window for viewing this badge's set as goal form.
         *
         * @param {Object} badge
         */
        openSetAsGoalModal(badge) {
            this.isModalOpen = true;
            this.currentBadge = badge;
            this.$refs.setAsGoalModal.open();
        },

        /**
         * Closes the modal window for the set as goal form.
         *
         * @param {Object} badge
         */
        closeSetAsGoalModal(badge) {
            this.isModalOpen = false;
            this.$refs.setAsGoalModal.close();
        },

        /**
         * Callback for when the filters for this badge list are updated.
         *
         * Fired from outer space, currently from library/Schoolbox/Learning/Badge/templates/list.twig
         *
         * @param {object} selectedFilters The filters that were selected
         */
        onFilterChange(event) {
            this.changeFilters(event.detail.selectedFilters);
        },

        /**
         * Update the display with the selected filters
         *
         * @param {object} selectedFilters The filters that were selected
         */
        changeFilters(selectedFilters) {
            this.selectedFilters = selectedFilters;
            this.showSkeletonLoader = true;
            this.filter_load(false);
            this.filter_emitChange();
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
        async deleteBadge() {
            this.$refs.toast.activate("Deleting badge...");
            try {
                const response = await fetch(this.currentBadge._links.delete, { method: "DELETE" });
                const responseText = await response.text();

                // The expected response is blank, with a HTTP status code in the 'success' range (200-299).
                // Any other response is treated as an error.
                if (response.ok && responseText === "") {
                    this.data = this.data.filter((badge) => badge.id !== this.currentBadge.id);
                    this.metadata.count -= 1;
                    this.$refs.toast.timeout("Badge successfully deleted", true);
                } else {
                    this.$refs.toast.timeout("Failed to delete badge", false);
                }
            } catch (error) {
                console.warn(error);
                this.$refs.toast.timeout("Failed to delete badge", false);
            } finally {
                this.closeDeleteModal();
            }
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
                    return Object.values(v1).some((v2) => Number(v2) === 1);
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
            Object.keys(this.selectedFilters).forEach((k) => (emptyQueryString[k] = ""));
            const url = new URL(window.location.href);
            if (url.searchParams.get("returnUrl") !== null) {
                emptyQueryString["returnUrl"] = url.searchParams.get("returnUrl");
            }
            return "?" + new URLSearchParams(emptyQueryString).toString();
        },

        /**
         * To ensure it's unchecked when 'Clear Selection' is selected in #badge-list-form
         *
         * @returns { Boolean }
         *
         * @returns { Boolean }
         */
        isInSelectedBadges(badgeId) {
            return this.selectedBadges.some((badge) => badge.id === badgeId);
        },

        /**
         * To notify of badge selection change
         *
         * @params { Boolean }
         */
        onBadgeSelected(badge, isChecked) {
            this.$emit("on-badge-selected", badge, isChecked);
        },

        /**
         * The modal is now completely closed so reset
         */
        onModalClosed() {
            this.isModalOpen = false;
        },
    },
};
</script>

<template>
    <div>
        <div v-if="showSkeletonLoader" class="row">
            <div class="small-12 columns">
                <div v-for="n in 3" class="card badge skeleton" :key="n">
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
                        <a :href="generateResetFilterUrl()" title="Reset filters">Reset filters</a> to see all available
                        badges.
                    </p>
                </template>

                <!-- If there are no badges in the system at all -->
                <template v-else>
                    <i class="icon-badge"></i>
                    <p>
                        <!-- the getBadgeCreateFormRoute could optionally contain a ?returnUrl=... appendage -->
                        <template v-if="getBadgeCreateFormRoute !== '' && getBadgeCreateFormRoute[0] !== '?'">
                            No badges have been created. Start by
                            <a :href="getBadgeCreateFormRoute" title="Create the first badge"
                                >creating the first badge</a
                            >.
                        </template>
                        <template v-else> There are no goals for you to choose yet. Please try again soon. </template>
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
                            <div
                                :class="{
                                    '': !showBadgeMenus,
                                    'actions-small-1': showBadgeMenus,
                                    'checkbox-content': !showBadgeMenus,
                                    'card-content': true,
                                }"
                            >
                                <div class="list-item">
                                    <template v-if="allowSelection">
                                        <input
                                            type="checkbox"
                                            :id="'selectBadge-' + String(badge.id)"
                                            :checked="isInSelectedBadges(badge.id)"
                                            @change="onBadgeSelected(badge, $event.target.checked)"
                                        />
                                        <label :for="'selectBadge-' + String(badge.id)" class="checkbox-label">
                                            {{ badge.name }}
                                        </label>
                                    </template>
                                    <h3 v-else>
                                        <a @click.prevent="openDetailModal(badge)" :title="badge.name">{{
                                            badge.name
                                        }}</a>
                                    </h3>
                                </div>
                                <nav v-if="showBadgeMenus">
                                    <sbx-pop-out
                                        :content-id="`badge-${badge.id}-dropdown`"
                                        :data-test="`sbx-pop-out-badge-${badge.id}-dropdown`"
                                    ></sbx-pop-out>
                                </nav>
                                <ul
                                    v-if="showBadgeMenus"
                                    :id="'badge-' + String(badge.id) + '-dropdown'"
                                    class="f-dropdown"
                                    data-dropdown-content
                                >
                                    <li>
                                        <a
                                            @click.prevent="openAwardAsAchievementModal(badge)"
                                            class="icon-achievement"
                                            title="Award achievement"
                                            >Award achievement</a
                                        >
                                    </li>
                                    <li v-if="badge.allowAsGoal">
                                        <a @click.prevent="openSetAsGoalModal(badge)" class="icon-goal" title="Set goal"
                                            >Set goal</a
                                        >
                                    </li>
                                    <li>
                                        <a
                                            @click.prevent="openAchievementWithdrawModal(badge)"
                                            class="icon-withdraw-achievement"
                                            title="Withdraw achievement"
                                            >Withdraw achievement</a
                                        >
                                    </li>
                                    <li v-if="badge.allowAsGoal">
                                        <a
                                            @click.prevent="openGoalWithdrawModal(badge)"
                                            class="icon-close"
                                            title="Remove goal"
                                            >Remove goal</a
                                        >
                                    </li>
                                    <li>
                                        <a :href="badge._links.update" class="icon-modify" title="Modify">Modify</a>
                                    </li>
                                    <li><a @click.prevent="openDeleteModal(badge)" class="icon-delete">Delete</a></li>
                                </ul>
                                <p class="meta" v-if="badge.category" :title="'Category: ' + badge.category.name">
                                    {{ badge.category.name }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Load more button, if there are more to load -->
                    <a
                        @click.prevent="filter_append(metadata.cursor.next, true, false)"
                        class="button load-more expand"
                        title="Load more"
                        v-if="metadata.cursor.next && data.length < metadata.count"
                        >Load more</a
                    >
                </section>
            </template>
        </div>

        <badge-modal :isOpen="isDetailModalOpen" :badge="currentBadge" @closed="onDetailModalClosed" />

        <reveal-modal
            size="medium"
            ref="awardAsAchievementModal"
            :closeOnBackgroundClick="false"
            @closed="onModalClosed"
        >
            <badge-award
                v-if="currentBadge"
                type="achievement"
                :key="'awardAchievement-' + String(currentBadge.id)"
                :badge="currentBadge"
                :endpoint="currentBadge._links.awardAsAchievement"
                :expiry="currentBadge.achievementExpiry"
                :cdn-prefix="this.cdnPrefix"
                :site-version="this.siteVersion"
                :is-modal-open="this.isModalOpen"
                @cancel="closeAwardAsAchievementModal"
                @done="closeAwardAsAchievementModal"
                placeholder="Search for users to award achievement to"
                toast-activate-message="Awarding achievement..."
                toast-success-message="Awarded achievement"
                toast-failure-message="Awarding achievement failed"
            >
                <template v-slot:header="slotProps">
                    <h2>
                        Award &lsquo;<b>{{ slotProps.badge.name }}</b
                        >&rsquo; as an Achievement
                    </h2>
                </template>
                <template v-slot:confirmButton> Award </template>
                <template v-slot:expiryMessage="slotProps">
                    <p class="meta" v-if="slotProps.expiryDate">
                        This achievement will expire
                        <relative-time :datetime="slotProps.expiryDate" date-only></relative-time>.
                    </p>
                </template>
            </badge-award>
        </reveal-modal>

        <reveal-modal size="medium" ref="setAsGoalModal" :closeOnBackgroundClick="false" @closed="onModalClosed">
            <badge-award
                v-if="currentBadge"
                type="goal"
                :key="'setGoal-' + String(currentBadge.id)"
                :badge="currentBadge"
                :endpoint="currentBadge._links.setAsGoal"
                :expiry="currentBadge.goalExpiry"
                :cdn-prefix="this.cdnPrefix"
                :site-version="this.siteVersion"
                @cancel="closeSetAsGoalModal"
                @done="closeSetAsGoalModal"
                placeholder="Search for users to set a goal for"
                toast-activate-message="Setting goal..."
                toast-success-message="Goal set"
                toast-failure-message="Setting goal failed"
            >
                <template v-slot:header="slotProps">
                    <h2>
                        Set &lsquo;<b>{{ slotProps.badge.name }}</b
                        >&rsquo; as a goal
                    </h2>
                </template>
                <template v-slot:confirmButton> Set goal </template>
                <template v-slot:expiryMessage="slotProps">
                    <p class="meta" v-if="slotProps.expiryDate">
                        This goal will expire
                        <relative-time :datetime="slotProps.expiryDate" date-only></relative-time>.
                    </p>
                </template>
            </badge-award>
        </reveal-modal>

        <reveal-modal ref="deleteModal">
            <template v-if="currentBadge">
                <h2>
                    Delete <strong>&lsquo;{{ currentBadge.name }}&rsquo;</strong>
                </h2>
                <div class="alert-box alert icon-alert">
                    WARNING! Any users who have been awarded this badge will have it removed from their collection.
                </div>
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
