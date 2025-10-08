<script setup lang="ts">
/**
 * To only show the nodes that are currently assigned to the current unit/course
 */

import { useCurrentNodesStore } from "../../store/csCurrentNodesStore";
import CsModalCurrentParentNode from "./cs-modal-current-parent-node.vue";

const store = useCurrentNodesStore();
</script>

<template>
    <div class="nodes-container" data-test="cs-modal-current-nodes-container">
        <ul v-if="store.hasLoaded" class="nodes l-flex-col">
            <cs-modal-current-parent-node v-for="node in store.filteredNodes" :key="node.id" :node="node" :depth="0" />
        </ul>
    </div>
</template>

<style scoped>
.nodes-container {
    max-height: 650px;
    overflow: auto;
    position: relative;
}

.nodes {
    /* remove the margin/spacing as the first node does not have a bullet */
    margin-inline-start: 0;
    padding-inline-start: 0;
}

.nodes > li {
    list-style-type: none; /* remove the bullets on the top level nodes */
}
</style>
