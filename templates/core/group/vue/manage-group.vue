<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { onMounted } from 'vue';
import ManageGroupHeading from './manage-group-heading.vue';
import ManageGroupAddMembers from './manage-group-add-members.vue';
import ManageGroupTable from './manage-group-table.vue';
import ManageGroupSelectMembers from './manage-group-select-members.vue';
import ManageGroupRemoveMembers from './manage-group-remove-members.vue';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    getMembersEndpoint: {
        type: String,
        required: true,
    },
    /**
     * the endpoint to remove members
     */
    removeMembersEndpoint: {
        type: String,
        required: true,
    },
});

onMounted(() => {
    $(document).foundation(); //to enable the Foundation features such as the drop down in -header and tabs in -add-members
});

</script>

<template>
    <manage-group-heading />
    <manage-group-add-members />
    <manage-group-table :get-members-endpoint="props.getMembersEndpoint" />
    <manage-group-select-members />
    <manage-group-remove-members :remove-members-endpoint="props.removeMembersEndpoint" />
</template>
