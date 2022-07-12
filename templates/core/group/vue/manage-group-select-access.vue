<script setup>

/**
 * Bulk update the currently selected members access rights
 */

import { ref, watch, computed } from 'vue';
import { useStore } from './store/manageGroupStore';
import { useMembersStore } from './store/manageGroupMembersStore';
import { useUpdateMembersStore } from './store/manageGroupUpdateMembersStore';
import AccessTypes from './manageGroupAccessTypes';


const props = defineProps({
    /**
     * To display the toast access status changes
     */
    toastEl: {
        required: true,
    },
});

const store = useStore(); //to disable buttons on processing
const membersStore = useMembersStore(); //to keep track of selectedMembers
const updateMembersStore = useUpdateMembersStore(); //to update member access
const areAllSelectedOnWaitingList = ref(false); //to determine whether we can show the approve membership button if all selected are awaiting membership
const isProcessing = ref(false); //to notify of process taking place
const changeMemberAccess = ref(''); //to bulk update members access rights

/**
 * if all selected members are on the waiting list we should show for bulk approval
 */
watch(() => membersStore.selectedMembers, () => {
    if (membersStore.selectedMembers.length > 0 && membersStore.selectedMembers.every((member) => member.accessType === 'waiting'))  {
        areAllSelectedOnWaitingList.value = true;
    } else {
        areAllSelectedOnWaitingList.value = false;
    }
}, { deep: true } );

/**
 * To instantly notify the user they've selected the approve button
 *
 * @returns { String }
 */
const appproveButtonText = computed(() => {
    if (areAllSelectedOnWaitingList.value && isProcessing.value) {
        return 'Approving...';
    } else {
        return 'Approve Membership';
    }
});

/**
 * so the selected members can be approved membership
 */
function onApproveMembersSelected () {
    updateMembersStore.updateSelectedMembersAccess(AccessTypes.READ);
}

/**
 * To update group members access
 */
function onMemberAccessChange () {
    if (changeMemberAccess.value !== '') {
        updateMembersStore.updateSelectedMembersAccess(this.changeMemberAccess);
    }
}

/**
 * so we can track the progress of the member access change
 * and update the UI accordingly
 */
updateMembersStore.$onAction(({ name, after, onError }) => {
    if (name === 'updateSelectedMembersAccess') {
        isProcessing.value = true;
        props.toastEl.activate('Updating...');
        after(() => {
            props.toastEl.timeout('Members successfully updated', true, 5000);
            isProcessing.value = false;
            changeMemberAccess.value = ''; //default back to blank on any update
        });
        onError((error) => {
            console.warn(error);
            props.toastEl.timeout('Unable to update members', false, 5000);
            isProcessing.value = false;
        });
    }
});

</script>

<template>
    <li v-if="areAllSelectedOnWaitingList">
        <button @click.prevent="onApproveMembersSelected" class="button submit icon-add" :disabled="store.isProcessing">{{ appproveButtonText }}</button>
    </li>
    <li v-else-if="membersStore.selectedMembers.length > 0">
        <select v-model="changeMemberAccess" :disabled="store.isProcessing" @change="onMemberAccessChange()">
            <option value="">Change Access</option>
            <option :value="AccessTypes.READ">Read Access</option>
            <option :value="AccessTypes.WRITE">Write Access</option>
            <option :value="AccessTypes.ADMIN">Group Admin</option>
        </select>
    </li>
</template>

<style lang="scss" scoped>

select, 
button {
    margin-bottom: 0; //to bring the bottom-drawer down, we don't need any margin currently
}

</style>