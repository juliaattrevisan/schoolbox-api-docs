<script setup>

/**
 * Manage member selection and the various actions that can occur based
 * on the members currently selected
 */

import { computed  } from 'vue';
import { useSelectedNodesStore } from './store/mcSelectedNodesStore';
import { useDeleteNodesStore } from './store/mcDeleteNodesStore';
import { useEnableNodesStore } from './store/mcEnableNodesStore';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';
import McSelectedNode from './mc-selected-node.vue';


const selectedNodesStore = useSelectedNodesStore(); //to keep track of selected nodes
const deleteNodesStore = useDeleteNodesStore();
const enableNodesStore = useEnableNodesStore();



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
 *
 * @param { boolean }
 */
 function onEnableNodesSelected (enabled) {
    enableNodesStore.prompt(enabled);
}

/**
 * So the selected nodes can be removed
 */
function onDeleteNodesSelected () {
    deleteNodesStore.prompt();
}

</script>

<template>
    <bottom-drawer :showDrawer="selectedNodesStore.hasNodes">
        <div v-if="selectedNodesStore.hasNodes" class="selected-nodes-container">
            <mc-selected-node
                v-for="node in selectedNodesStore.nodes"
                :key="node.id"
                :node="node"
            />
        </div>
        <ul class="flex-list buttons">
            <li v-if="selectedNodesStore.allNodesEnabled">
                <button class="icon-view-hollow" @click.prevent="onEnableNodesSelected(false)" @keyup.enter="onEnableNodesSelected(false)">Disable {{ selectedNodesSummary }}</button>
            </li>
            <li v-else-if="selectedNodesStore.allNodesDisabled">
                <button class="icon-view" @click.prevent="onEnableNodesSelected(true)" @keyup.enter="onEnableNodesSelected(true)">Enable {{ selectedNodesSummary }}</button>
            </li>
            <li>
                <button class="icon-delete" @click.prevent="onDeleteNodesSelected" @keyup.enter="onDeleteNodesSelected">Delete {{ selectedNodesSummary }}</button>
            </li>
        </ul>
    </bottom-drawer>
</template>

<style lang="scss" scoped>

.selected-nodes-container {
    border: 1px solid var(--form-border-color);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    min-height: 50px;
    padding: 0.5rem;
}

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
