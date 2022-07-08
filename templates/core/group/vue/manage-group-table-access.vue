<script setup>

/**
 * To view and update each members status 
 */

import { ref, computed } from 'vue';
import { useStore } from './store/manageGroupStore';
import { useUpdateStore } from './store/manageGroupUpdateStore';
import AccessTypes from './manageGroupAccessTypes';


const props = defineProps({
    /**
     * Contains all the row data
     */
    row: {
        type: Object,
        required: true,
    },
    /**
     * To display the toast access status changes
     */
    toastEl: {
        required: true,
    },
});

const store = useStore();
const updateStore = useUpdateStore();
const isProcessing = ref(false); //to update the button text when an event is taking place

/**
 * To approve a members membership request and set them as read by default
 * 
 * @param { Object } related row 
 */
function onApproveMembershipSelected (row) {
    updateStore.updateMemberAccess(row, AccessTypes.READ);
}

/**
 * To update a group members access
 * 
 * @param { Object } related row 
 */
function onMemberAccessChange (row) {
    updateStore.updateMemberAccess(row, row.accessType);
}

/**
 * so we can track the progress of the member access change
 * and update the UI accordingly
 */
updateStore.$onAction(({ name, args, after, onError }) => {
    const updatedRow = args[0];
    if (name === 'updateMemberAccess' && updatedRow.id === props.row.id) { //as each row is subscribed to onAction, we have to check for the selected row
        isProcessing.value = true;
        props.toastEl.activate('Updating...');
        after(() => {
            props.toastEl.timeout('Member successfully updated', true, 5000);
            isProcessing.value = false;
        });
        onError((error) => {
            console.warn(error);
            props.toastEl.timeout('Unable to update members', false, 5000);
            isProcessing.value = false;
        });
    }
});

/**
 * To instantly notify the user that the members access is being updated 
 * by updating the button text
 *
 * @returns { String }
 */
const approveButtonText = computed(() => isProcessing.value ? 'Approving...' : 'Approve Membership');

</script>

<template>
    <button 
        v-if="props.row.accessType === 'waiting'"
        class="button expand"
        title="Approve Membership"
        :disabled="store.isProcessing"
        @click.prevent="onApproveMembershipSelected(props.row)"
    >
        {{ approveButtonText }}
    </button>
    <select 
        v-else 
        v-model="props.row.accessType"
        :disabled="store.isProcessing"
        @change="onMemberAccessChange(props.row)"
    >
        <option :value="AccessTypes.READ">Read Access</option>
        <option :value="AccessTypes.WRITE">Write Access</option>
        <option :value="AccessTypes.ADMIN">Group Admin</option>
    </select>
</template>

<style scoped>

</style>