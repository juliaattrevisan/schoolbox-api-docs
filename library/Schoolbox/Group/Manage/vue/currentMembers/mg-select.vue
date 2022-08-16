<script setup>

/**
 * Manage member selection and the various actions that can occur based
 * on the members currently selected
 */

import { ref, computed, watch, onMounted  } from 'vue';
import { useStore } from '../store/manageGroupStore';
import { useMembersStore } from '../store/mgMembersStore';
import { useRemoveMembersStore } from '../store/mgRemoveMembersStore';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';
import MgSelectAccess from './mg-select-access.vue';
import Endpoints from '../mgEndpoints';


const store = useStore(); //to disable butons on processing
const membersStore = useMembersStore(); //to keep track of selectedMembers
const removeMembersStore = useRemoveMembersStore(); //to remove selectedMembers
const isShiftKeySelected = ref(false);
const shiftSelectNoteEl = ref(null);

onMounted(() => {
    initShiftKeyDetection();
});

/**
 * to hide a UI note informing of shift-selection once pressed once
 */
function initShiftKeyDetection () {
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Shift') {
            isShiftKeySelected.value = true;
        }
    });
    document.addEventListener('keyup', (event) => {
        if (event.key === 'Shift') {
            isShiftKeySelected.value = false;
        }
    });
}

/**
 * remove the note once a selection with shift key down has been detected
 */
watch(() => membersStore.selectedMembers, () => {
    if (membersStore.selectedMembers.length > 1 && isShiftKeySelected.value) { //to hide a UI note, notifying of availability of shift-select
        shiftSelectNoteEl.value.remove();
    }
});

/**
 * For proper count display, eg: 'No Members / 1 Member' / '4 Members'
 *
 * @returns { String }
 */
const selectedMembersCountWithRowName = computed(() => {
    if (membersStore.selectedMembersCount === 0) {
        return `No Members`;
    } else {
        return `${membersStore.selectedMembersCount} ${membersStore.selectedMembersPlural}`;
    }
});

/**
 * Dispatch to manage-group.vue so the selection can be cleared
 */
function onClearMemberSelection () {
    membersStore.clearSelectedMembers();
}

/**
 * Email the selected group members, passing through the 
 * required url params
 * 
 */
function onEmailMembersSelected () {
    window.location.href = `${Endpoints.SEND_EMAIL}?folderId=${store.id}&userIds=${membersStore.selectedMemberIds.join(',')}`;
}

/**
 * So the selected members can be removed
 */
function onRemoveMembersSelected () {
    removeMembersStore.promptRemoveSelectedMembers();
}

</script>

<template>
    <bottom-drawer :showDrawer="membersStore.selectedMembersCount > 0">
        <h3 class="selected-count"><em>{{ selectedMembersCountWithRowName }} selected</em></h3>
        <span ref="shiftSelectNoteEl" class="shift-select-note">Use Shift+Select to select multiple members</span>
        <button @click.prevent="onClearMemberSelection" class="button-clear" :disable="store.isProcessing">Clear member selection</button>
        <ul class="flex-list">
            <mg-select-access />
            <li>
                <button @click.prevent="onEmailMembersSelected" class="button submit icon-email" :disabled="store.isProcessing">Email {{ membersStore.selectedMembersPlural }}</button>
            </li>
            <li>
                <button @click.prevent="onRemoveMembersSelected" class="button submit icon-delete" :disabled="store.isProcessing">Remove {{ membersStore.selectedMembersPlural }}</button>
            </li>
        </ul>
    </bottom-drawer>
</template>

<style lang="scss" scoped>

.selected-count {
    margin-right: 1rem;
}

.button-clear, .button-delete, .selected-count {
    display: inline-block;
}

.shift-select-note {
    color: #9a9a9a;
    font-size: 13px;
    font-style: italic;
    margin-right: 1rem;
}

.button-clear {
    background: none;
    font-size: 0.8rem;
    padding: 0px;
    vertical-align: baseline;
    &:hover {
        background: none;
    }
}

select,
button {
    margin-bottom: 0; //to bring the bottom-drawer down, we don't need any margin currently
}

</style>
