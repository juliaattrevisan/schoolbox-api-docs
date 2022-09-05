<script setup>

/**
 * Manage member selection and the various actions that can occur based
 * on the members currently selected
 */

import { ref, computed, watch, onMounted  } from 'vue';
// import { useStore } from '../store/manageGroupStore';
import { useSelectedNodesStore } from './store/mcSelectedNodesStore';
import { useDeleteNodeStore } from './activeNode/store/mcDeleteNodeStore';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';


// const store = useStore(); //to disable butons on processing
const selectedNodesStore = useSelectedNodesStore(); //to keep track of selected nodes
const deleteNodeStore = useDeleteNodeStore();



/**
 * For proper count display, eg: 'No nodes / 1 Member' / '4 nodes'
 *
 * @returns { String }
 */
const selectedNodesSummary = computed(() => {
    if (selectedNodesStore.hasNoNodes) {
        return `No nodes`;
    } else {
        return `${selectedNodesStore.count} ${selectedNodesStore.singleOrPlural}`;
    }
});

/**
 * Dispatch to manage-group.vue so the selection can be cleared
 */
function onClearMemberSelection () {
    selectedNodesStore.empty();
}

/**
 * So the selected members can be removed
 */
function onDeleteNodesSelected () {
    deleteNodeStore.prompt();
}

</script>

<template>
    <bottom-drawer :showDrawer="selectedNodesStore.hasNodes">
        <h3 class="selected-count"><em>{{ selectedNodesSummary }} selected</em></h3>
        <button @click.prevent="onClearMemberSelection" class="button-clear" :disabled="false">Clear selection</button>
        <ul class="flex-list">
            <!-- <li>
                <button @click.prevent="onEmailnodesSelected" class="button submit icon-email" :disabled="false">Email {{ selectedNodesStore.selectednodesPlural }}</button>
            </li> -->
            <li>
                <button @click.prevent="onDeleteNodesSelected" class="button submit icon-delete" :disabled="false">Delete {{ selectedNodesSummary }}</button>
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
