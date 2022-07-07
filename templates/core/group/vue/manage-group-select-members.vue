<script setup>

/**
 * Manage member selection and the various actions that can occur based
 * on the members currently selected
 */

import { ref, computed, watch, onMounted  } from 'vue';
import { useStore } from './manageGroupStore';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';
import AccessTypes from './manageGroupAccessTypes';


const props = defineProps({
    /**
     * To display the toast access status changes
     */
    toastEl: {
        required: true,
    },
});

const store = useStore(); //to keep track of selectedMembers
const isShiftKeySelected = ref(false);
const shiftSelectNoteEl = ref(null);
const areAllSelectedOnWaitingList = ref(false); //to determine whether we can show the approve membership button if all selected are awaiting membership
const isProcessing = ref(false); //to disable buttons while a process is in place

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
watch(() => store.selectedMembers, () => {
    if (store.selectedMembers.length > 1 && isShiftKeySelected.value) { //to hide a UI note, notifying of availability of shift-select
        shiftSelectNoteEl.value.remove();
    } 
    if (store.selectedMembers.length > 0 && store.selectedMembers.every((member) => member.accessType === 'waiting'))  {
        areAllSelectedOnWaitingList.value = true;
    } else {
        areAllSelectedOnWaitingList.value = false;
    }
}, { deep: true } );

/**
 * To track how many are selected 
 *
 * @returns { Number }
 */
const selectedMembersCount = computed(() => store.selectedMembers.length);

/**
 * For proper count display, eg: 'Member' / 'Members'
 *
 * @returns { String }
 */
const selectedMembersPlural = computed(() => selectedMembersCount.value > 1 ? 'Members' : 'Member');

/**
 * For proper count display, eg: 'No Members / 1 Member' / '4 Members'
 *
 * @returns { String }
 */
const selectedMembersCountWithRowName = computed(() => {
    if (selectedMembersCount.value === 0) {
        return `No Members`;	
    } else {
        return `${selectedMembersCount.value} ${selectedMembersPlural.value}`;	
    } 
});

/**
 * Dispatch to manage-group.vue so the selection can be cleared
 */
function onClearMemberSelection () {
    store.clearSelectedMembers();
}

/**
 * Email the selected group members
 */
function onEmailMembersSelected () {
    console.warn('onEmailMembersSelected');
}

/**
 * so the selected members can be approved membership
 */
function onApproveMembersSelected () {
    store.updateSelectedMembersAccess(AccessTypes.READ);
}

const changeMemberAccess = ref('');

/**
 * To update group members access
 */
function onMemberAccessChange () {
    if (changeMemberAccess.value !== '') {
        store.updateSelectedMembersAccess(this.changeMemberAccess);
    }
}

/**
 * so we can track the progress of the member access change
 * and update the UI accordingly
 */
store.$onAction(({ name, after, onError }) => {
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

/**
 * So the selected members can be removed
 */
function onRemoveMembersSelected () {
    store.promptRemoveSelectedMembers();
}

</script>

<template>
    <bottom-drawer :showDrawer="selectedMembersCount > 0">
        <h3 class="selected-count"><em>{{ selectedMembersCountWithRowName }} selected</em></h3>
        <span ref="shiftSelectNoteEl" class="shift-select-note">Use Shift+Select to select multiple members</span>
        <button @click.prevent="onClearMemberSelection" class="button-clear" :disable="isProcessing">Clear member selection</button>
        <ul class="flex-list">
            <li v-if="areAllSelectedOnWaitingList">
                <button @click.prevent="onApproveMembersSelected" class="button submit icon-add" :disabled="store.isProcessing">Approve Membership</button>
            </li>
            <li v-else-if="selectedMembersCount > 0">
                <select v-model="changeMemberAccess" :disabled="store.isProcessing" @change="onMemberAccessChange()">
                    <option value="">Change Access</option>
                    <option :value="AccessTypes.READ">Read Access</option>
                    <option :value="AccessTypes.WRITE">Write Access</option>
                    <option :value="AccessTypes.ADMIN">Group Admin</option>
                </select>
            </li>
            <li>
                <button @click.prevent="onEmailMembersSelected" class="button submit icon-email" :disabled="store.isProcessing">Email {{ selectedMembersPlural }}</button>
            </li>
            <li>
                <button @click.prevent="onRemoveMembersSelected" class="button submit icon-delete" :disabled="store.isProcessing">Remove {{ selectedMembersPlural }}</button>
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

.flex-list {
    gap: 0.5rem; //add a bit more space between the buttons so each is better defined
}

select, 
button {
    margin-bottom: 0; //to bring the bottom-drawer down, we don't need any margin currently
}

</style>