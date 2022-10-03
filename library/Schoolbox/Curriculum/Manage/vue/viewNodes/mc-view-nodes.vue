<script setup>

/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected for further travesal or editing
 */

import { ref, computed, watch } from 'vue';
import { useNodesStore } from '../store/mcNodesStore';
import { useViewNodesStore } from './store/mcViewNodesStore';
import { useSearchStore } from '../store/mcSearchStore';
import LoadingTable from '@schoolboxVue/components/loading/loading-table.vue';
import McViewNode from './mc-view-node.vue'


const nodesStore = useNodesStore();
const viewNodesStore = useViewNodesStore();
const searchStore = useSearchStore();
const depth = ref(0); //to increment each time we go down the node tree to visually indicate children
const nodesContainerEl = ref(null); //to scroll back to top on any selection change

/**
 * To further clarify where the current search term hasn't found any node matches
 *
 * @returns { String }
 */
const currentSelectedNodeTitle = computed(() => viewNodesStore.current.name);

/**
 * Clear the current search query
 */
function onClearSearchSelected () {
    searchStore.clear();
}

/**
 * to scroll back to top on any selection change, otherwise the
 * scroll may be at the bottom on change, which is a bit odd
 */
watch(() => viewNodesStore.nodesWithSearch, () => {
    if (nodesContainerEl.value) { //may not always exists in the dom
        nodesContainerEl.value.scrollTop = 0;
    }
});

</script>

<template>
    <div v-if="searchStore.isProcessing || nodesStore.isLoading">
        <loading-table />
    </div>
    <div v-else-if="searchStore.hasNoResults" class="empty-state-flex-centered">
        <i class="icon-curriculum"></i>
        <p>No Curriculum in: {{ currentSelectedNodeTitle }} match your current search.</p>
        <button @click.prevent="onClearSearchSelected" @keyup.enter="onClearSearchSelected" title="Clear the current search">Clear search</button>
    </div>
    <ul v-else ref="nodesContainerEl">
        <mc-view-node
            v-for="node in viewNodesStore.nodes"
            :key="node.id"
            :node="node"
            :depth="depth"
        />
    </ul>
</template>

<style lang="scss" scoped>

ul {
    list-style: none;
    margin: 0 0 1rem 0;
}

</style>