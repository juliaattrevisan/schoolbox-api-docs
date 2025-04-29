<script setup lang="ts">
/**
 * To only show the nodes that are currently assigned to the unit
 */

import { useUnitNodesStore } from "../../store/csUnitNodesStore";
import CsModalUnitNode from "./cs-modal-unit-node.vue";

const store = useUnitNodesStore();
</script>

<template>
    <div class="nodes-container">
        <ul v-if="store.hasLoaded" class="nodes l-flex-col">
            <cs-modal-unit-node v-for="node in store.nodes" :key="node.id" :node="node" :depth="0" />
        </ul>
    </div>
</template>

<style scoped>
.nodes-container {
    max-height: 650px;
    overflow: auto;
}
.nodes {
    /* move the spacing in a bit */
    margin-inline-start: var(--spacing-s);
    margin-block-end: 0;
    padding-inline-start: var(--spacing-s);
}
.nodes li ul {
    margin-inline-start: var(--spacing-xs);
}
</style>
