<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { ref, onBeforeMount, onMounted } from 'vue';
import Endpoints from './manageGroupEndpoints';
import { useStore } from './store/manageGroupStore';
import ManageGroupHeading from './manage-group-heading.vue';
import ManageGroupAddMembers from './manage-group-add-members.vue';
import ManageGroupMembers from './manage-group-members.vue';
import ManageGroupSelect from './manage-group-select.vue';
import ManageGroupRemoveMembers from './manage-group-remove-members.vue';
import ManageGroupUpdateSettings from './manage-group-update-settings.vue';
import Toast from '@schoolboxVue/components/toast.vue';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    groupDataEndpoint: {
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
    /**
     * the endpoint to add members
     */
    addMembersEndpoint: {
        type: String,
        required: true,
    },
    /**
     * the endpoint to add members
     */
    updateSettingsEndpoint: {
        type: String,
        required: true,
    },
});

const toastEl = ref(null);

/**
 * set the endpoints here, and the stores can just reference the object
 * rather than having to pass them through as props as these values wont change
 */
onBeforeMount(() => {
    Endpoints.GROUP_DATA = props.groupDataEndpoint;
    Endpoints.REMOVE_MEMBERS = props.removeMembersEndpoint;
    Endpoints.UPDATE_MEMBERS = props.updateMembersEndpoint;
    Endpoints.ADD_MEMBERS = props.addMembersEndpoint;
    Endpoints.UPDATE_SETTINGS = props.updateSettingsEndpoint;
});

/**
 * load the data and ensure any foundation features work 
 */
onMounted(() => {
    const store = useStore();
    store.loadData();
    $(document).foundation(); 
});

</script>

<template>
    <manage-group-heading :toast-el="toastEl" />
    <manage-group-add-members :toast-el="toastEl" />
    <manage-group-members :toast-el="toastEl" />
    <manage-group-select :toast-el="toastEl" />
    <manage-group-remove-members :toast-el="toastEl" />
    <manage-group-update-settings :toast-el="toastEl" />
    <toast ref="toastEl"></toast>
</template>
