<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { onBeforeMount, onMounted } from 'vue';
import Endpoints from './manageGroupEndpoints';
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
    /**
     * the endpoint to update members
     */
    updateMembersEndpoint: {
        type: String,
        required: true,
    },
});

/**
 * set the endpoints here, and the stores can just reference the object
 * rather than having to pass them through as props as these values wont change
 */
onBeforeMount(() => {
    Endpoints.GET_MEMBERS = props.getMembersEndpoint;
    Endpoints.REMOVE_MEMBERS = props.removeMembersEndpoint;
    Endpoints.UPDATE_MEMBERS = props.updateMembersEndpoint;
});

/**
 * to enable the Foundation features such as the drop down in -header and tabs in -add-members
 */
onMounted(() => {
    $(document).foundation(); 
});

</script>

<template>
    <manage-group-heading />
    <manage-group-add-members />
    <manage-group-table />
    <manage-group-select-members />
    <manage-group-remove-members />
</template>
