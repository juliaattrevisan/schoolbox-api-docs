<script setup>

/**
 * Manage member selection and the various actions that can occur based
 * on the members currently selected
 */

import { computed  } from 'vue';
import { useSelectedNodesStore } from './selectedNodes/store/mcSelectedNodesStore';
import { useDeleteSelectedNodesStore } from './selectedNodes/store/mcDeleteSelectedNodesStore';
import { useEnableSelectedNodesStore } from './selectedNodes/store/mcEnableSelectedNodesStore';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';


const selectedNodesStore = useSelectedNodesStore(); //to keep track of selected nodes
const deleteSelectedNodesStore = useDeleteSelectedNodesStore();
const enableSelectedNodesStore = useEnableSelectedNodesStore();



/**
 * For proper count display, eg: 'No nodes / 1 Member' / '4 nodes'
 *
 * @returns { String }
 */
const selectedNodesSummary = computed(() => {
    if (selectedNodesStore.hasNoNodes) {
        return `No nodes`;
    } else {
        return selectedNodesStore.countWithName;
    }
});

/**
 * Clear the current selection
 */
function onClearMemberSelection () {
    selectedNodesStore.empty();
}

/**
 * So the selected nodes can be enabled / disabled
 */
 function onEnableNodesSelected () {
    enableSelectedNodesStore.prompt();
}

/**
 * So the selected nodes can be removed
 */
function onDeleteNodesSelected () {
    deleteSelectedNodesStore.prompt();
}

</script>

<template>
    <bottom-drawer :showDrawer="selectedNodesStore.hasNodes">
        <h3 class="selected-count"><em>{{ selectedNodesSummary }} selected</em></h3>
        <button class="button-clear" @click.prevent="onClearMemberSelection">Clear selection</button>
        <ul class="flex-list buttons">
            <li>
                <button class="icon-email"  @click.prevent="onEnableNodesSelected" @keyup.enter="onEnableNodesSelected">Enable {{ selectedNodesSummary }}</button>
            </li>
            <li>
                <button class="icon-delete" @click.prevent="onDeleteNodesSelected" @keyup.enter="onDeleteNodesSelected">Delete {{ selectedNodesSummary }}</button>
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
