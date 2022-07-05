<script setup>

/**
 * Manage member selection and the various actions that can occur based
 * on the members currently selected
 */

import { ref, computed, watch, onMounted  } from 'vue';
import { useStore } from './manageGroupStore';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';


const store = useStore(); //to keep track of selectedMembers
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
watch(() => store.selectedMembers, () => {
    if (store.selectedMembers.length > 1 && isShiftKeySelected.value) { //to hide a UI note, notifying of availability of shift-select
        shiftSelectNoteEl.value.remove();
    } 
});

/**
 * To track how many are selected 
 *
 * @returns { Number }
 */
const selectedMembersCount = computed(() => store.selectedMembers.length);

/**
 * For proper count display, eg: '1 member' / '4 members'
 *
 * @returns { String }
 */
const selectedMembersCountWithRowName = computed(() => {
    if (selectedMembersCount.value === 0) {
        return `No members`;	
    } else if (selectedMembersCount.value === 1) {
        return `${selectedMembersCount.value} member`;	
    } else {
        return `${selectedMembersCount.value} members`;	
    }
});

/**
 * To determine whether we can show the approve membership 
 * button in the bottom drawer
 *
 * @returns { Boolean }
 */
const areAllSelectedOnWaitingList = computed(() => {
    return store.selectedMembers.every((member) => member.access === 'waiting');
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
    // 
}

/**
 * Dispatch to manage-group.vue so the selected members can be removed
 */
function onRemoveMembersSelected () {
    store.promptRemoveSelectedMembers();
}

</script>

<template>
    <bottom-drawer :showDrawer="selectedMembersCount > 0">
        <h3 class="selected-count"><em>{{ selectedMembersCountWithRowName }} selected</em></h3>
        <span ref="shiftSelectNoteEl" class="shift-select-note">Use Shift+Select to select multiple members</span>
        <button @click.prevent="onClearMemberSelection" class="button-clear">Clear member selection</button>
        <ul class="flex-list buttons">
            <li v-if="areAllSelectedOnWaitingList">
                <button @click.prevent="onRemoveMembersSelected" class="button submit icon-add">Approve Membership</button>
            </li>
            <li>
                <button @click.prevent="onEmailMembersSelected" class="button submit icon-email">Email {{ selectedMembersCountWithRowName }}</button>
            </li>
            <li>
                <button @click.prevent="onRemoveMembersSelected" class="button submit icon-delete">Remove {{ selectedMembersCountWithRowName }}</button>
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

</style>