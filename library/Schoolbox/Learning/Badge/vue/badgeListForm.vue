<script>

// import {stringify} from 'qs';
// import axios from 'axios';
// import Toast from "@schoolbox/toast";
import RevealModal from "@schoolbox/reveal-modal";
// import BadgeAward from "./badgeAward";
// import BadgeDetail from "./badgeDetail";
// import BadgeAchievementWithdrawModal from "./badge-achievement-withdraw-modal";
// import BadgeGoalWithdrawModal from "./badge-goal-withdraw-modal";
// import BadgeImage from './badge-image';
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
        // BadgeImage,
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
    },
    data() {
        return {
        }
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

        selectedBadges() {
            return this.$refs.badgeList.selectedBadges();
        }
    },
}
</script>

<template>
    <div>
        <badge-list ref="badgeList"
                    :get-badges-route="getBadgesRoute"
                    :allow-selection="true"
                    :show-badge-menus="false"
        ></badge-list>

        <ul>
            <li v-for="badge in selectedBadges">{{ badge.name }}</li>
        </ul>

        <button class="submit full-width" >Award selected achievement(s)</button>
    </div>
</template>
