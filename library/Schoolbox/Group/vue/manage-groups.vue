<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Manage your groups
 */

import ManageGroupsHeading from "./manage-groups-heading";
import ManageGroupsEmpty from "./manage-groups-empty";
import ManageGroupsItem from "./manage-groups-item";
import selectionTypes from "../js/manageGroupsSelectionTypes";
import Toast from "@schoolboxVue/components/toast";

export default {
    components: {
        ManageGroupsHeading,
        ManageGroupsEmpty,
        ManageGroupsItem,
        Toast,
    },

    props: {
        endpointAdd: {
            //to join a group
            type: String,
            required: true,
        },
        endpointDelete: {
            //to leave a group
            type: String,
            required: true,
        },
        endpointNotify: {
            //to update a groups notification status
            type: String,
            required: true,
        },
        endpointFavourite: {
            //to update a groups favourite status
            type: String,
            required: true,
        },
        groups: {
            //all the groups that the user can access
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            selectedGroups: selectionTypes.GROUPS_IM_IN, //current group selection as a String
            filteredGroups: [], //selectedGroups, then also with the search filter considered and as an Array
            isSearchActive: false, //to update the empty state messages to consider searching
        };
    },

    computed: {
        /**
         * Whether to show the empty state
         *
         * @returns { Number }
         */
        isGroupsEmpty() {
            return this.filteredGroups.length === 0;
        },
    },

    created() {
        window.onhashchange = this.checkForValidHash; //account for history changes or for example selection the 'my favourite groups' link in the side menu
        this.checkForValidHash();
    },

    methods: {
        /**
         * The hash is used for initial group selection display, so we can land straight
         * on my favourite groups, groups pending membership etc
         *
         * @param { String }
         */
        checkForValidHash() {
            const hash = window.location.hash.substring(1); //ditch the # of the hash
            const isHashValid = Object.values(selectionTypes).includes(hash); //check if the hash is one of the valid selectionTypes
            if (isHashValid) {
                this.selectedGroups = hash; //only update if valid, otherwise just leave as is
            }
        },

        /**
         * A <select> change has taken place
         *
         * @param { String }
         */
        onGroupsSelected(groupsSelection) {
            this.selectedGroups = groupsSelection;
            window.location.hash = this.selectedGroups;
        },

        /**
         * Both the current <select> status and search query considered to show the current group list
         *
         * @param { Array }
         * @param { Boolean }
         */
        onGroupsFiltered(groups, isSearchActive = false) {
            this.filteredGroups = groups;
            this.isSearchActive = isSearchActive;
        },

        /**
         * Notify that a group edit action has started taking place
         *
         * @param { String }
         */
        onGroupEditStart(text) {
            this.$refs.toast.activate(text);
        },

        /**
         * A group edit action was successful
         *
         * @param { String }
         */
        onGroupEditSuccess(text) {
            this.$refs.toast.timeout(text, true, 3000);
        },

        /**
         * A group edit action has an error
         *
         * @param { String }
         */
        onGroupEditError(text) {
            this.$refs.toast.timeout(text, false, 3000);
        },
    },
};
</script>

<template>
    <div>
        <manage-groups-heading
            :groups="groups"
            :selected-groups="selectedGroups"
            @on-groups-selected="onGroupsSelected"
            @on-groups-filtered="onGroupsFiltered"
        />
        <manage-groups-empty
            v-if="isGroupsEmpty"
            :selected-groups="selectedGroups"
            :is-search-active="isSearchActive"
        />
        <div v-else class="l-grid l-grid--12">
            <manage-groups-item
                v-for="group in filteredGroups"
                :key="group.id"
                :endpoint-add="endpointAdd"
                :endpoint-delete="endpointDelete"
                :endpoint-notify="endpointNotify"
                :endpoint-favourite="endpointFavourite"
                :group="group"
                @on-edit-start="onGroupEditStart"
                @on-edit-success="onGroupEditSuccess"
                @on-edit-error="onGroupEditError"
            />
        </div>
        <toast ref="toast"></toast>
    </div>
</template>

<style>
body {
    overflow-y: scroll; /*to prevent jarring shifts when moving between group selections */
}
</style>
