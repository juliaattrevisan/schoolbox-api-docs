<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { onMounted } from 'vue';
import ManageGroupHeading from './manage-group-heading.vue';
import ManageGroupAddMembers from './manage-group-add-members.vue';
import ManageGroupTable from './manage-group-table.vue';
import ManageGroupSelectMembers from './manage-group-select-members.vue';

const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    endpoint: {
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
    <manage-group-table :endpoint="props.endpoint" />
    <manage-group-select-members />
</template>
