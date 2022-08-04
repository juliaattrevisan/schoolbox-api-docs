<script>

/**
 * Show when there's no groups matching the current selection/search criteria
 */

import selectionTypes from '../js/manageGroupsSelectionTypes';


export default {

    props: {
        selectedGroups: { //current groups selection
            type: String, 
            required: true,
        },
        isSearchActive: { //to update the empty state messages to consider searching
            type: Boolean, 
            required: true,
        }
    },

    computed: {

        /**
         * Notify the user when there's no groups within the current selection
         * 
         * @returns { String } 
         */
        selectionMessage () {
            const emptyMessage = {
                [selectionTypes.ALL_GROUPS]: `No groups are available`,
                [selectionTypes.MY_FAVOURITE_GROUPS]: `You don't currently have any favourite groups`,
                [selectionTypes.GROUPS_IM_IN]: `You aren't currently in any groups`,
                [selectionTypes.GROUPS_I_CAN_JOIN]: `There are currently no other groups you can join`,
                [selectionTypes.GROUPS_PENDING_MEMBERSHIP]: `You have no groups currently pending membership`,
            };
            return emptyMessage[this.selectedGroups];
        },

        /**
         * Whether any groups matches the current search
         * 
         * @returns { String } 
         */
        searchMessage () {
            if (this.isSearchActive) {
                return ' that match your search'
            } else {
                return '';
            }
        },

        /**
         * Change the text depending on the status of the group selection and current search
         * 
         * @returns { String } 
         */
        emptyMessage () {
            return `${this.selectionMessage}${this.searchMessage}.`;
        },

    }

}

</script>

<template>
    <div class="row">
        <div class="column small-12">
            <div class="empty-state">
                <i class="icon-group"></i>
                <p>{{ emptyMessage }}</p>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>