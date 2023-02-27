<script setup lang="ts">
/**
 * Shows the activity count types when available and can be selected
 * to reveal the node description and connected material
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import { getUsageIcon, getTotalUsage } from "@schoolboxCurriculum/js/nodeUtils";
import { useNodeUsageStore } from "../activeNode/store/mcNodeUsageStore";

interface Props {
    node: Node; // the assigned node to display
    title: string; // the already computed node title
    isViewing: boolean; // to disable certain events when viewing the node
    isUpdating: boolean; // to disable certain events when updating the node
    isAdding: boolean; // to disable certain events when adding a node
}

const props = defineProps<Props>();
const nodeUsageStore = useNodeUsageStore();

/**
 * to open up or close the view modal
 */
function onViewToggleSelected() {
    if (props.isViewing) {
        nodeUsageStore.cancel();
    } else {
        nodeUsageStore.prompt(props.node);
    }
}
</script>

<template>
    <button
        v-if="getTotalUsage(props.node) > 0"
        class="usage-counts"
        :title="`View ${props.title}`"
        :disabled="props.isUpdating || props.isAdding"
        @click.prevent="onViewToggleSelected"
    >
        <span
            v-if="props.node._usageCounts?.course > 0"
            :class="`label neutral ${getUsageIcon(ActivityTypes.COURSE)}`"
            title="Courses"
            >{{ props.node._usageCounts.course }}</span
        >
        <span
            v-if="props.node._usageCounts?.unit > 0"
            :class="`label neutral ${getUsageIcon(ActivityTypes.UNIT)}`"
            title="Units"
            >{{ props.node._usageCounts.unit }}</span
        >
        <span
            v-if="props.node._usageCounts?.assessment > 0"
            :class="`label neutral ${getUsageIcon(ActivityTypes.LEARNING_ACTIVITY)}`"
            title="Learning activity"
            >{{ props.node._usageCounts.assessment }}</span
        >
        <span
            v-if="props.node._usageCounts?.badge > 0"
            :class="`label neutral ${getUsageIcon(ActivityTypes.BADGE)}`"
            title="Badges"
            >{{ props.node._usageCounts.badge }}</span
        >
    </button>
    <button
        v-if="getTotalUsage(props.node) > 0 || node.description !== ''"
        :class="`button-action-option ${props.isViewing ? 'icon-close' : 'icon-curriculum-details'}`"
        :title="`View ${props.title}`"
        :disabled="props.isUpdating || props.isAdding"
        data-test-view
        @click.prevent="onViewToggleSelected"
    >
        View {{ props.title }}
    </button>
</template>

<style lang="scss" scoped>
@import "./style/mcViewNode";
@import "../style/mcIconColours";
.button-action-option {
    @extend .button;
    color: var(--content-ui-foreground);
    font-size: 0rem;
    opacity: 0.7;
    padding: 0px 2px 0px 12px;
    &:hover {
        opacity: 1;
    }
}

.usage-counts {
    @extend .button;
    display: flex;
    gap: 0.5rem;
    min-width: auto;
    overflow: visible;
    span {
        background: none;
        padding: 0.375rem 0.25rem;
        margin: 0;
    }
}
</style>
