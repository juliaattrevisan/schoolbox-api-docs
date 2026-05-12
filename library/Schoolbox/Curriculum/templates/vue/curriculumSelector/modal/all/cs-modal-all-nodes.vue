<script setup lang="ts">
/**
 * The filtered results from using the <selects> or search <input>
 * From here curriculum nodes can be selected
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import { useModalViewNodesStore } from "../../store/csModalViewNodesStore";
import { useNodesStore } from "../../store/csAllNodesStore";
import { useModalSearchStore } from "../../store/csModalSearchStore";
import CsAllModalNode from "./cs-modal-all-node.vue";

const modalViewNodesStore = useModalViewNodesStore();
const modalSearchStore = useModalSearchStore();
const nodesStore = useNodesStore();
const depth = window.Vue.ref(0); // to increment each time we go down the node tree to visually indicate children
const nodesContainerEl = window.Vue.ref<HTMLUListElement | null>(null); // to scroll back to top on any selection change

/**
 * Clear the current search query
 */
function onClearSearchSelected(): void {
    modalSearchStore.clear();
}

/**
 * to scroll back to top on any selection change, otherwise the
 * scroll may be at the bottom on change, which is a bit odd
 */
window.Vue.watch(
    () => modalViewNodesStore.nodes as Node[],
    () => {
        if (nodesContainerEl.value) {
            // may not always exists in the dom
            nodesContainerEl.value.scrollTop = 0;
        }
    },
);
</script>

<template>
    <div v-if="nodesStore.hasNoCurriculum" class="empty-state-flex-centered">
        <sbx-icon name="curriculum" size="2.5rem"></sbx-icon>
        <p>There's currently no Curriculum loaded to display.</p>
    </div>
    <div v-else-if="nodesStore.isLoading || modalSearchStore.isLoading">
        <loading-table />
    </div>
    <div v-else-if="modalSearchStore.hasNoResults" class="empty-state-flex-centered">
        <sbx-icon name="curriculum" size="2.5rem"></sbx-icon>
        <p>No Curriculum matches your current search.</p>
        <button title="Clear the current search" @click.prevent="onClearSearchSelected">Clear search</button>
    </div>
    <ul v-else ref="nodesContainerEl">
        <cs-all-modal-node v-for="node in modalViewNodesStore.nodes" :key="node.id" :node="node" :depth="depth" />
    </ul>
</template>

<style lang="scss" scoped>
$height: 500px;

ul {
    list-style: none;
    margin: 0 0 1rem 0;
    max-height: $height; /*restrict the size of the modal, it'll scroll when larger */
    min-height: $height;
    overflow-x: hidden; /*prevent any horizontal animations from showing a scrollbar */
    overflow-y: auto;
    position: relative;
}

.empty-state-flex-centered {
    min-height: $height;
}
</style>
