<script setup>

/**
 * Bulk update the currently selected members access rights
 */

import { ref, watch, computed } from 'vue';
import { useStore } from '../store/manageGroupStore';
import { useMembersStore } from '../store/mgMembersStore';
import { useUpdateMembersStore } from '../store/mgUpdateMembersStore';
import { useRejectMembersStore } from '../store/mgRejectMembersStore';
import AccessTypes from './mgAccessTypes';


const store = useStore(); //to disable buttons on processing
const membersStore = useMembersStore(); //to keep track of selectedMembers
const updateMembersStore = useUpdateMembersStore(); //to update member access
const rejectMembersStore = useRejectMembersStore(); //to reject the selected members
const areAllSelectedOnWaitingList = ref(false); //to determine whether we can show the approve membership button if all selected are awaiting membership
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
    if (areAllSelectedOnWaitingList.value && updateMembersStore.isSelectedProcessing) {
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
        updateMembersStore.updateSelectedMembersAccess(changeMemberAccess.value);
    }
}

/**
 * so we can track the progress of the member access change
 * and update the UI accordingly
 */
updateMembersStore.$onAction(({ name, after, onError }) => {
    if (name === 'updateSelectedMembersAccess') {
        store.toastEl.activate('Updating...');
        after(() => {
            store.toastEl.timeout('Members successfully updated', true, 5000);
            changeMemberAccess.value = ''; //default back to blank on any update
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout(error, false, 5000);
            changeMemberAccess.value = ''; //default back to blank on any update
        });
    }
});

/**
 * To instantly notify the user they've selected the reject button
 *
 * @returns { String }
 */
const rejectButtonText = computed(() => {
    if (areAllSelectedOnWaitingList.value && rejectMembersStore.isSelectedProcessing) {
        return 'Rejecting...';
    } else {
        return 'Reject Membership';
    }
});

/**
 * reject the selected members
 */
function onRejectMembersSelected () {
    rejectMembersStore.rejectSelectedMembers();
}

/**
 * so we can track the progress of the member access change
 * and update the UI accordingly
 */
rejectMembersStore.$onAction(({ name, after, onError }) => {
    if (name === 'rejectSelectedMembers') {
        store.toastEl.activate('Rejecting...');
        after(() => {
            store.toastEl.timeout('Members successfully rejected', true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout('Unable to reject members', false, 5000);
        });
    }
});

</script>

<template>
    <template v-if="areAllSelectedOnWaitingList">
        <li>
            <button @click.prevent="onApproveMembersSelected" class="button submit icon-add" :disabled="store.isProcessing">{{ appproveButtonText }}</button>
        </li>
        <li>
            <button @click.prevent="onRejectMembersSelected" class="button submit icon-close" :disabled="store.isProcessing">{{ rejectButtonText }}</button>
        </li>
    </template>
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
