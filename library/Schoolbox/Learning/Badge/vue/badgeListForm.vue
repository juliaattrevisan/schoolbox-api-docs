<script>

// import {stringify} from 'qs';
// import axios from 'axios';
// import Toast from "@schoolbox/toast";
import RevealModal from "@schoolbox/reveal-modal";
// import BadgeAward from "./badgeAward";
// import BadgeDetail from "./badgeDetail";
// import BadgeAchievementWithdrawModal from "./badge-achievement-withdraw-modal";
// import BadgeGoalWithdrawModal from "./badge-goal-withdraw-modal";
import BadgeImage from './badge-image';
import BadgeList from './badgeList';

export default {

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [],

    components: {
        BadgeList,
        // BadgeAward,
        // BadgeDetail,
        // BadgeAchievementWithdrawModal,
        // BadgeGoalWithdrawModal,
        BadgeImage,
        RevealModal,
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
         * The planned recipients of the award.
         */
        users: {
            type: Array,
            required: true,
        },

        /**
         * Where does the user get redirected to on successful save or
         * cancellation of the form?
         */
        returnUrl: {
            type: String,
            default: '/',
        }
    },
    data() {
        return {
            selectedBadges: [],
        }
    },
    computed: {
        window: () => window,
    },
    methods: {
        /**
         * Callback for when the filters for the badge list form are updated:
         * passed directly to the badge list.
         *
         * Fired from outer space (i.e. from the outside window).
         *
         * @param {object} selectedFilters The filters that were selected
         */
        onFilterChange(selectedFilters) {
            this.$refs.badgeList.onFilterChange(selectedFilters);
        },

        /**
         * Listener for the badge list's change event, emitted when a badge is
         * selected or deselected.
         */
        updateSelectedBadges() {
            this.selectedBadges = this.$refs.badgeList.selectedBadges();
        },

        /**
         * Helper for rendering a user's full name
         *
         * @param {Object} user
         * @return {String}
         */
        fullName(user) {
            return (user.title + ' ' + user.firstname + ' ' + user.lastname).trim();
        },

        /**
         * Submit action for this form.
         *
         * Award badges, and then go back to the return URL once done.
         */
        onSubmit() {
            this.window.location.href = this.returnUrl;
        },
    },
}
</script>

<template>
    <div>
        <badge-list ref="badgeList"
                    :get-badges-route="getBadgesRoute"
                    :allow-selection="true"
                    :show-badge-menus="false"
                    @change="updateSelectedBadges"
        ></badge-list>

        <ul class="flex-list buttons">
            <li>
                <button class="submit full-width"
                        @click.prevent="$refs.awardModal.open"
                        :disabled="selectedBadges.length === 0"
                >Award selected achievement(s)</button>
            </li>
            <li><a :href="returnUrl" class="button">Cancel</a></li>
        </ul>

        <reveal-modal ref="awardModal" size="large">
            <h2>Confirm achievement(s)</h2>
            <p class="meta">Awarding the following achievement(s):</p>
            <ul class="grid medium-block-grid-2 large-block-grid-4">
                <li v-for="badge in selectedBadges">
                    <div class="compact card small-12 badge-card">
                        <div>
                            <badge-image :badge="badge"></badge-image>
                            <h3>{{ badge.name }}</h3>
                        </div>
                    </div>
                </li>
            </ul>

            <p class="meta">To the following user(s):</p>
            <ul class="grid medium-block-grid-2 large-block-grid-4">
                <li v-for="user in users">
                    <div class="card small-12">
                        <img :src="'/portrait.php?id=' + user.id + '&size=square32'" :alt="fullName(user)">
                        <p>{{ fullName(user) }}</p>
                    </div>
                </li>
            </ul>

            <ul class="flex-list buttons">
                <li><button @click.prevent="onSubmit" class="submit">Confirm</button></li>
                <li><a @click.prevent="$refs.awardModal.close" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
    </div>
</template>
