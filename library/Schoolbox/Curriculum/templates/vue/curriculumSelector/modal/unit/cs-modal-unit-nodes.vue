<script setup lang="ts">
/**
 * To only show the nodes that are currently assigned to the unit
 */

import { useUnitNodesStore } from "../../store/csUnitNodesStore";
import CsModalUnitNode from "./cs-modal-unit-node.vue";

const store = useUnitNodesStore();
</script>

<template>
    <ul v-if="store.hasLoaded" class="nodes l-flex-col">
        <cs-modal-unit-node v-for="node in store.nodes" :key="node.id" :node="node" />
    </ul>
</template>

<style scoped>
.nodes {
    /* move the spacing in a bit */
    margin-inline-start: var(--spacing-m);
    margin-block-end: 0;
    padding-inline-start: var(--spacing-m);
}
.nodes li ul {
    margin-inline-start: var(--spacing-xs);
}
</style>
