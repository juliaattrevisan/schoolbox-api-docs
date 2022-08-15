<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { ref, onBeforeMount, onMounted } from 'vue';
import Endpoints from './manageGroupEndpoints';
import { useStore } from './store/manageGroupStore';
import { useMembersStore } from './store/manageGroupMembersStore';
import ManageGroupHeading from './manage-group-heading.vue';
import LoadingTable from '@schoolboxVue/components/loading/loading-table';
import ManageGroupAddMembers from './manage-group-add-members.vue';
import ManageGroupMembers from './manage-group-members.vue';
import ManageGroupSelect from './manage-group-select.vue';
import ManageGroupRemoveMembers from './manage-group-remove-members.vue';
import ManageGroupUpdateSettings from './manage-group-update-settings.vue';
import ManageGroupDisbandGroup from './manage-group-disband-group.vue';
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
     * the endpoint to update settings
     */
    updateSettingsEndpoint: {
        type: String,
        required: true,
    },
    /**
     * the endpoint to disband the group
     */
    disbandGroupEndpoint: {
        type: String,
        required: true,
    },
    /**
     * the endpoint to send email
     */
    sendEmailEndpoint: {
        type: String,
        required: true,
    },
});

const store = useStore();
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
    Endpoints.DISBAND_GROUP = props.disbandGroupEndpoint;
    Endpoints.SEND_EMAIL = props.sendEmailEndpoint;
});

/**
 * Start loading the group data
 */
onMounted(() => {
    store.toastEl = toastEl;
    checkForStorybook();
    store.loadData();
});

/**
 * When switching between stories, we need to reset the data models
 * as they persist between the stories otherwise
 */
function checkForStorybook () {
    if (typeof STORYBOOK_ENV !== 'undefined') {
        store.$reset();
        useMembersStore().$reset();
    }
}

/**
 * Reload the page to try loading again
 */
function onRefreshSelected () {
    location.reload();
}

</script>

<template>
    <manage-group-heading />
    <div v-if="store.isLoading" class="row">
        <div class="small-12 island">
            <loading-table  />
        </div>
    </div>
    <div v-else-if="store.hasLoadError" class="row">
        <div class="small-12 island">
            <section class="content">
                <div tableData-alert class="alert-box alert">
                    Sorry, we are currently unable to load this groups data. Please <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected">refresh the page</a> to try again and contact your support if the problem persists.
                </div>
            </section>
        </div>
    </div>
    <template v-else-if="store.hasLoadedOk">
        <manage-group-add-members />
        <manage-group-members />
        <manage-group-select />
        <manage-group-remove-members />
        <manage-group-update-settings />
        <manage-group-disband-group />
        <toast ref="toastEl"></toast>
    </template>
</template>
