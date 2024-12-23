<script setup lang="ts">
/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected for further travesal or editing
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import { useNodesStore } from "../store/mcNodesStore";
import { useViewNodesStore } from "./store/mcViewNodesStore";
import { useSearchStore } from "../store/mcSearchStore";
import McViewNode from "./mc-view-node.vue";

const nodesStore = useNodesStore();
const viewNodesStore = useViewNodesStore();
const searchStore = useSearchStore();
const depth = window.Vue.ref(0); // to increment each time we go down the node tree to visually indicate children
const nodesContainerEl = window.Vue.ref<HTMLDivElement | null>(null); // to scroll back to top on any selection change

/**
 * Clear the current search query
 */
function onClearSearchSelected(): void {
    searchStore.clear();
}

/**
 * to scroll back to top on any selection change, otherwise the
 * scroll may be at the bottom on change, which is a bit odd
 */
window.Vue.watch(
    () => viewNodesStore.nodes,
    () => {
        if (nodesContainerEl.value) {
            // may not always exists in the dom
            nodesContainerEl.value.scrollTop = 0;
        }
    },
);
</script>

<template>
    <div class="small-12 columns" data-test-view-nodes>
        <div v-if="nodesStore.isLoading || searchStore.isLoading">
            <loading-table />
        </div>
        <div v-else-if="searchStore.hasNoResults" class="empty-state-flex-centered">
            <i class="icon-curriculum"></i>
            <p>No Curriculum matches your current search.</p>
            <button title="Clear the current search" data-test-clear-search @click.prevent="onClearSearchSelected">
                Clear search
            </button>
        </div>
        <div v-else ref="nodesContainerEl" data-test="mc-view-nodes-list">
            <mc-view-node
                v-for="node in viewNodesStore.nodes"
                :key="node.id"
                :node="node"
                :depth="depth"
                data-test-view-node
            />
        </div>
    </div>
</template>
