<script setup lang="ts">
/**
 * Shows the activity count types when available and can be selected
 * to reveal the node description and connected material
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import NodeUsageSummary from "@schoolboxCurriculum/vue/usage/node-usage-summary.vue";
import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";

interface Props {
    node: Node; // the assigned node to display
    title: string; // the already computed node title
    isViewing: boolean; // to disable certain events when viewing the node
    isUpdating: boolean; // to disable certain events when updating the node
    isAdding: boolean; // to disable certain events when adding a node
}

const props = defineProps<Props>();
const modalNodeUsageStore = useModalNodeUsageStore();

/**
 * to open up or close the view modal
 */
async function onViewToggleSelected(): Promise<void> {
    if (props.isViewing) {
        modalNodeUsageStore.cancel();
    } else {
        await modalNodeUsageStore.prompt(props.node);
    }
}
</script>

<template>
    <node-usage-summary
        :node="props.node"
        :title="props.title"
        :is-viewing="props.isViewing"
        :is-disabled="props.isUpdating || props.isAdding"
        @selected="onViewToggleSelected"
    />
</template>
