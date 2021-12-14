<script>

/**
 * Groups page title, search and toggles
 */

import selectionTypes from '../js/manageGroupsSelectionTypes';
import enrolmentTypes from '../js/manageGroupsEnrolmentTypes';


export default {

    props: {
        groups: { //all the groups that the user can access
            type: Array,
            required: true,
        },
    },

    data () {
        return {
            query: '', //to further refine group search
            currentGroupSelection: 'allGroups' //current group selection
        }
    },

    computed: {

        /**
         * Change the place holder depending on the current selection
         * 
         * @returns { String } 
         */
        pageTitle () {
            const pageTitle = {
                [selectionTypes.ALL_GROUPS]: `All groups`,
                [selectionTypes.MY_FAVOURITE_GROUPS]: `My favourite groups`,
                [selectionTypes.GROUPS_IM_IN]: `Groups I'm in`,
                [selectionTypes.GROUPS_I_CAN_JOIN]: `Groups I can join`,
                [selectionTypes.GROUPS_PENDING_MEMBERSHIP]: `Groups pending membership`,
            };
            return pageTitle[this.currentGroupSelection];
        },

        /**
         * Change the place holder depending on the current selection
         * 
         * @returns { String } 
         */
        inputPlaceholder () {
            const inputPlaceholder = {
                [selectionTypes.ALL_GROUPS]: `Search all groups...`,
                [selectionTypes.MY_FAVOURITE_GROUPS]: `Search my favourite groups...`,
                [selectionTypes.GROUPS_IM_IN]: `Search groups I'm in...`,
                [selectionTypes.GROUPS_I_CAN_JOIN]: `Search groups I can join...`,
                [selectionTypes.GROUPS_PENDING_MEMBERSHIP]: `Search groups pending membership...`,
            };
            return inputPlaceholder[this.currentGroupSelection];
        },

        /**
         * Grups filtered to the current selection
         * 
         * @returns { Array }
         */
        currentGroups () {
            const currentGroups = {
                [selectionTypes.ALL_GROUPS]: () => this.groups,
                [selectionTypes.MY_FAVOURITE_GROUPS]: () => this.groups.filter((group) => group.isFavourite),
                [selectionTypes.GROUPS_IM_IN]: () => this.groups.filter((group) => group.enrolmentStatus === enrolmentTypes.ENROLLED),
                [selectionTypes.GROUPS_I_CAN_JOIN]: () => this.groups.filter((group) => group.enrolmentStatus === enrolmentTypes.NOT_ENROLLED),
                [selectionTypes.GROUPS_PENDING_MEMBERSHIP]: () => this.groups.filter((group) => group.enrolmentStatus === enrolmentTypes.PENDING),
            };
            return currentGroups[this.currentGroupSelection]();
        },

        /**
         * Retrieve only the groups that match the search query
         * 
         * @returns { Array } 
         */
        groupsFiltered () {
            let groupsFiltered = [];
            if (this.query.length <= 2) {
                groupsFiltered = this.currentGroups;
            }
            groupsFiltered = this.currentGroups.filter((group) => group.name.toLowerCase().includes(this.query));
            return groupsFiltered;
        },

    },

    watch: {

        /**
         * As currentGroups and groupsFiltered are not within the <template>
         * we need to watch in order to dispatch changes to manage-groups.vue
         * 
         * @param { Array } 
         */
        groupsFiltered (newGroups) {
            this.$emit('on-selection-change', newGroups);
        }

    },

    created () {
        this.selectionTypes = selectionTypes; //to access within the template
    },

}

</script>

<template>
    <div>
        <div class="row">
            <div class="column small-12">
                <h1>{{ pageTitle }}</h1>
            </div>
        </div>
        <div class="row">
            <div class="column small-8">
                <input
                    v-model="query"
                    @input="$event.target.composing = false"
                    :placeholder="inputPlaceholder"
                    type="text"
                />
            </div>
            <div class="column small-4">
                <select v-model="currentGroupSelection">
                    <option :value="selectionTypes.ALL_GROUPS">All groups</option>
                    <option :value="selectionTypes.MY_FAVOURITE_GROUPS">My favourite groups</option>
                    <option :value="selectionTypes.GROUPS_IM_IN">Groups I'm in</option>
                    <option :value="selectionTypes.GROUPS_I_CAN_JOIN">Groups I can join</option>
                    <option :value="selectionTypes.GROUPS_PENDING_MEMBERSHIP">Groups pending membership</option>
                </select>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>