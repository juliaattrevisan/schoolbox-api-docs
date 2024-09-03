<script setup>
/**
 * To view and update each members status
 */

import { useStore } from "../store/manageGroupStore";
import { useUpdateMembersStore } from "../store/mgUpdateMembersStore";
import { useRejectMembersStore } from "../store/mgRejectMembersStore";
import AccessTypes from "./mgAccessTypes";

const props = defineProps({
    /**
     * Contains all the member data
     */
    member: {
        type: Object,
        required: true,
    },
});

const store = useStore();
const isProcessing = window.Vue.ref(false); //so only the just selected member is active

/*****************************************************************
 *
 * Update member access
 *
 ****************************************************************/

const updateMembersStore = useUpdateMembersStore();

/**
 * To instantly notify the user that the members access is being updated
 * by updating the button text
 *
 * @returns { String }
 */
const approveButtonText = window.Vue.computed(() =>
    updateMembersStore.isProcessing && isProcessing.value ? "Approving..." : "Approve",
);

/**
 * To approve a members membership request and set them as read by default
 *
 * @param { Object } related member
 */
function onApproveMembershipSelected(member) {
    updateMembersStore.updateMemberAccess(member, AccessTypes.READ);
}

/**
 * To update a group members access
 *
 * @param { Object } related member
 */
function onMemberAccessChange(member) {
    updateMembersStore.updateMemberAccess(member, member.accessType);
}

/**
 * so we can track the progress of the member access change
 * and update the UI accordingly
 */
updateMembersStore.$onAction(({ name, args, after, onError }) => {
    const updatedMember = args[0];
    if (name === "updateMemberAccess" && updatedMember.id === props.member.id) {
        //as each member is subscribed to onAction, we have to check for the selected member
        isProcessing.value = true;
        store.toastEl.activate("Updating...");
        after(() => {
            store.toastEl.timeout("Member successfully updated", true, 5000);
            isProcessing.value = false;
        });
        onError((error) => {
            store.toastEl.timeout(error.message, false, 5000);
            isProcessing.value = false;
        });
    }
});

/*****************************************************************
 *
 * Reject member access
 *
 ****************************************************************/

const rejectMembersStore = useRejectMembersStore();

/**
 * To instantly notify the user that the member is being rejected
 * by updating the button text
 *
 * @returns { String }
 */
const rejectButtonText = window.Vue.computed(() =>
    rejectMembersStore.isProcessing && isProcessing.value ? "Rejecting..." : "Reject",
);

/**
 * To reject a members request and remove the user
 *
 * @param { Object } related member
 */
function onRejectMembershipSelected(member) {
    rejectMembersStore.rejectMember(member);
}

/**
 * so we can track the progress of the member rejection
 * and update the UI accordingly
 */
rejectMembersStore.$onAction(({ name, args, after, onError }) => {
    const rejectedMember = args[0];
    if (name === "rejectMember" && rejectedMember.id === props.member.id) {
        //as each member is subscribed to onAction, we have to check for the selected member
        isProcessing.value = true;
        store.toastEl.activate("Rejecting...");
        after(() => {
            store.toastEl.timeout("Member successfully rejected", true, 5000);
            isProcessing.value = false;
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout("Unable to reject member", false, 5000);
            isProcessing.value = false;
        });
    }
});
</script>

<template>
    <ul v-if="props.member.accessType === 'none'" class="flex-list">
        <li>
            <button
                class="button expand icon-add"
                title="Approve Membership"
                :disabled="store.isProcessing"
                @click.prevent="onApproveMembershipSelected(props.member)"
            >
                {{ approveButtonText }}
            </button>
        </li>
        <li>
            <button
                class="button expand icon-close"
                title="Reject Membership"
                :disabled="store.isProcessing"
                @click.prevent="onRejectMembershipSelected(props.member)"
            >
                {{ rejectButtonText }}
            </button>
        </li>
    </ul>
    <select
        v-else
        v-model="props.member.accessType"
        :disabled="store.isProcessing"
        @change="onMemberAccessChange(props.member)"
    >
        <option :value="AccessTypes.READ">Read Access</option>
        <option :value="AccessTypes.WRITE">Write Access</option>
        <option v-if="props.member.isStaff || !store.groupStaffOnly" :value="AccessTypes.ADMIN">Group Admin</option>
    </select>
</template>

<style scoped></style>
