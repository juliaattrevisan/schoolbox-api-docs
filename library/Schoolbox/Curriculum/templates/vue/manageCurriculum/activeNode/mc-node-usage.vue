<script setup lang="ts">
/**
 * to view a nodes description and all the places the node is used
 */

import NodeUsageDetails from "@schoolboxCurriculum/vue/usage/node-usage-details.vue";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
// import { useNodeUsageStore } from "./store/mcNodeUsageStore";

interface Props {
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();
const activeNodeStore = useActiveNodeStore();
// const nodeUsageStore = useNodeUsageStore();
</script>

<template>
    <node-usage-details :node="activeNodeStore.node" :depth="props.depth" />
</template>
