<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { ref, computed, onMounted  } from 'vue';
import ManageGroupActions from './manage-group-actions.vue';
import ManageGroupAddMembers from './manage-group-add-members.vue';

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
    $(document).foundation(); //to enable the Foundation features such as the drop down in -actions and tabs in -add-members
});

</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item small-12 columns">
            <h1>Manage Group</h1>
        </div>
        <manage-group-actions />
    </div><!-- row actions-small-1 -->
    <div class="row">
        <div class="small-12 island">
            <manage-group-add-members />
            <h2 class="subheader">Current Members</h2>
            <section>
            </section>
        </div><!-- row -->
    </div><!-- small-12 island -->
</template>

<style scoped>
    
</style>/