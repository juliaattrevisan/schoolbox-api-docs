<script setup lang="ts">
/**
 * to view a nodes description and all the places the node is used
 */

import type { BadgeUsageObject, Usage } from "@schoolboxCurriculum/types/Node";
import NodeUsageDetails from "@schoolboxCurriculum/vue/usage/node-usage-details.vue";
import { useModalStore } from "../store/csModalStore";
import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import { useModalActiveNodeStore } from "../store/csModalActiveNodeStore";

interface Props {
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();
const modalStore = useModalStore();
const modalActiveNodeStore = useModalActiveNodeStore();
const modalNodeUsageStore = useModalNodeUsageStore();

/**
 * This will trigger the sequential reveal modal in cs-modal to
 * move to the next in sequence, hiding the curriculum browser
 * and showing the badge modal
 * @param usage - the selected usage
 */
function onUsageSelected(usage: Usage): void {
    modalStore.setSelectedBadge(usage.object as BadgeUsageObject);
}
</script>

<template>
    <div>
        <node-usage-details
            :node="modalActiveNodeStore.node"
            :depth="props.depth"
            :is-processing="modalNodeUsageStore.isProcessing"
            :has-load-error="modalNodeUsageStore.hasLoadError"
            @select="onUsageSelected"
        />
    </div>
</template>
