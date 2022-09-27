<script setup>

/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected for further travesal or editing
 */

import { ref, watch } from 'vue';
import { useViewNodesStore } from './store/mcViewNodesStore';
import McViewNode from './mc-view-node.vue'


const viewNodesStore = useViewNodesStore();
const depth = ref(0); //to increment each time we go down the node tree to visually indicate children
const nodesContainerEl = ref(null); //to scroll back to top on any selection change


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
    <ul ref="nodesContainerEl">
        <mc-view-node
            v-for="node in viewNodesStore.nodesWithSearch"
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