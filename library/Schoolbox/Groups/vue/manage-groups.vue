<script>

/**
 * Manage your groups
 */

import ManageGroupsHeading from './manage-groups-heading';
import ManageGroupsEmpty from './manage-groups-empty';
import ManageGroupsItem from './manage-groups-item';
import selectionTypes from '../js/manageGroupsSelectionTypes';
import Toast from '@vue/components/toast';


export default {

    components: {
        ManageGroupsHeading,
        ManageGroupsEmpty,
        ManageGroupsItem,
        Toast
    },

    props: {
        endpoint: { //to join/leave/favourite etc a group
            type: String,
            required: true,
        },
        groups: { //all the groups that the user can access
            type: Array,
            required: true,
        },
    },

    data () {
        return {
            selectedGroups: selectionTypes.GROUPS_IM_IN, //current group selection as a String
            filteredGroups: null, //selectedGroups, then also with the search filter considered and as an Array
            isSearchActive: false, //to update the empty state messages to consider searching
        }
    },

    computed: {

        /**
         * Whether to show the empty state
         * 
         * @returns { Number }
         */
        isGroupsEmpty () {
            return this.filteredGroups.length === 0;
        },

    },

    created () {
        this.filteredGroups = this.groups; //the initial group state
        location.hash = this.selectedGroups;
    },

    methods: { 

        /**
         * A <select> change has taken place
         * 
         * @param { String }
         */
        onGroupsSelected (groupsSelection) {
            this.selectedGroups = groupsSelection;
            location.hash = this.selectedGroups;
        },

        /**
         * The current <select> status and search query considered
         * 
         * @param { Array }
         * @param { Boolean }
         */
        onGroupsFiltered (groups, isSearchActive) {
            this.filteredGroups = groups;
            this.isSearchActive = isSearchActive;
        },

        /**
         * Notify that a group edit action has started taking place
         * 
         * @param { String }
         */
        onGroupEditStart (text) {
            this.$refs.toast.activate(text);
        },

        /**
         * A group edit action was successful 
         * 
         * @param { String }
         */
        onGroupEditSuccess (text) {
            this.$refs.toast.timeout(text, true, 3000);
        },

        /**
         * A group edit action has an error
         * 
         * @param { String }
         */
        onGroupEditError (text) {
            this.$refs.toast.timeout(text, false, 3000);
        },

    },

}

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
        <div v-else class="row">
            <manage-groups-item 
                v-for="group in filteredGroups" 
                :key="group.id" 
                :endpoint="endpoint"
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