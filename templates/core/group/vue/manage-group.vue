<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { ref, onBeforeMount, onMounted } from 'vue';
import Endpoints from './manageGroupEndpoints';
import ManageGroupHeading from './manage-group-heading.vue';
import ManageGroupAdd from './manage-group-add.vue';
import ManageGroupTable from './manage-group-table.vue';
import ManageGroupSelect from './manage-group-select.vue';
import ManageGroupRemove from './manage-group-remove.vue';
import Toast from '@schoolboxVue/components/toast.vue';


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
    /**
     * the endpoint to add members
     */
    addMembersEndpoint: {
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
    Endpoints.GET_MEMBERS = props.getMembersEndpoint;
    Endpoints.REMOVE_MEMBERS = props.removeMembersEndpoint;
    Endpoints.UPDATE_MEMBERS = props.updateMembersEndpoint;
    Endpoints.ADD_MEMBERS = props.addMembersEndpoint;
});

/**
 * to enable the Foundation features such as the drop down in -header and tabs in -add-members
 */
onMounted(() => {
    $(document).foundation(); 
});

</script>

<template>
    <manage-group-heading :toast-el="toastEl" />
    <manage-group-add :toast-el="toastEl" />
    <manage-group-table :toast-el="toastEl" />
    <manage-group-select :toast-el="toastEl" />
    <manage-group-remove :toast-el="toastEl" />
    <toast ref="toastEl"></toast>
</template>
