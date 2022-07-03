<script setup>

/**
 * Manage member selection and the various events that can occur
 */

import { ref, computed, watch, onMounted  } from 'vue';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';


const props = defineProps({
    /**
     * the members currently selected in the table
     */
    selectedMembers: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['on-selection-cleared', 'on-remove-selected']);

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
watch(() => props.selectedMembers, () => {
    if (props.selectedMembers.length > 1 && isShiftKeySelected.value) { //to hide a UI note, notifying of availability of shift-select
        shiftSelectNoteEl.value.remove();
    } 
});

/**
 * To track how many are selected 
 *
 * @returns { Number }
 */
const selectedMembersCount = computed(() => {
    return props.selectedMembers.length;
});

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
    return props.selectedMembers.every((member) => member.access === 'waiting');
});

/**
 * Dispatch to manage-group.vue so the selection can be cleared
 */
function onClearMemberSelection () {
    emit('on-selection-cleared'); 
}

/**
 * Dispatch to manage-group.vue so the selected members can be removed
 */
function onRemoveMembersSelected () {
    emit('on-remove-selected'); 
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