<script setup lang="ts">
/**
 * to view a nodes description and all the places the node is used
 */

import type { BadgeUsageObject, Usage } from "@schoolboxCurriculum/types/Node";
import NodeUsageDetails from "@schoolboxCurriculum/vue/usage/node-usage-details.vue";
import BadgeModal from "@schoolboxBadge/vue/badge-modal.vue";
import { useNodeUsageStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcNodeUsageStore";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";

interface Props {
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();
const activeNodeStore = useActiveNodeStore();
const nodeUsageStore = useNodeUsageStore();
const isBadgeModalOpen = window.Vue.ref(false); // whether the modal to display the badge details is open
const currentBadge = window.Vue.ref<BadgeUsageObject | null>(null); // the badge to display in the modal when a badge usage is selected

/**
 * Opens a modal window for viewing this badge's full details.
 * @param { BadgeUsageObject } badge - the badge to display
 */
function openDetailModal(badge: BadgeUsageObject) {
    currentBadge.value = badge;
    isBadgeModalOpen.value = true;
}

/**
 * Open a new page or open the badge modal
 * @param { Usage } usage - the selected usage
 */
function onUsageSelected(usage: Usage) {
    openDetailModal(usage.object as BadgeUsageObject);
}

/**
 * the badge modal is closed, so we should set the boolean to
 * false so we can open it again
 */
function onDetailModalClosed() {
    isBadgeModalOpen.value = false;
}
</script>

<template>
    <div>
        <node-usage-details
            :node="activeNodeStore.node"
            :depth="props.depth"
            :is-processing="nodeUsageStore.isProcessing"
            :has-load-error="nodeUsageStore.hasLoadError"
            @select="onUsageSelected"
        />
        <badge-modal :is-open="isBadgeModalOpen" :badge="currentBadge" @closed="onDetailModalClosed" />
    </div>
</template>
