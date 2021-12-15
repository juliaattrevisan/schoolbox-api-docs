<script>

/**
 * Show when there's no groups matching the current selection/search criteria
 */

import selectionTypes from '../js/manageGroupsSelectionTypes';


export default {

    props: {
        selectedGroups: { //current <select> selection
            type: String, 
            required: true,
        }
    },

    computed: {

        /**
         * Change the place holder depending on the current selection
         * 
         * @returns { String } 
         */
        emptyMessage () {
            const emptyMessage = {
                [selectionTypes.ALL_GROUPS]: `There are currently no groups that match your search`,
                [selectionTypes.MY_FAVOURITE_GROUPS]: `You don't currently have any favourite groups.`,
                [selectionTypes.GROUPS_IM_IN]: `You aren't currently in any groups.`,
                [selectionTypes.GROUPS_I_CAN_JOIN]: `There are currently no other groups you can join.`,
                [selectionTypes.GROUPS_PENDING_MEMBERSHIP]: `You have no groups currently pending membership.`,
            };
            return emptyMessage[this.selectedGroups];
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