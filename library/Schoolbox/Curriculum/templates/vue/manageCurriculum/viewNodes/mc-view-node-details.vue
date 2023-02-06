<script setup lang="ts">
/**
 * To edit an existing node or add a new subsection to a node
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { hasUsage } from "@schoolboxCurriculum/js/nodeUtils";
import { useNodeDetailsStore } from "../activeNode/store/mcNodeDetailsStore";
import McViewNodeActionsUsage from "./mc-view-node-actions-usage.vue";

interface Props {
    node: Node; // the assigned node to display
    title: string; // the already computed node title
    isViewing: boolean; // to disable certain events when viewing the node
    isUpdating: boolean; // to disable certain events when updating the node
    isAdding: boolean; // to disable certain events when adding a node
}

const props = defineProps<Props>();
const nodeDetailsStore = useNodeDetailsStore();

/**
 * to open up or close the view modal
 */
function onViewToggleSelected() {
    if (props.isViewing) {
        nodeDetailsStore.cancel();
    } else {
        nodeDetailsStore.prompt(props.node);
    }
}
</script>

<template>
    <button
        class="usage-counts"
        :title="`View ${props.title}`"
        :disabled="props.isUpdating || props.isAdding"
        @click.prevent="onViewToggleSelected"
    >
        <span v-if="props.node._usageCounts.course > 0" class="label neutral icon-course-builder" title="Courses">{{
            props.node._usageCounts.course
        }}</span>
        <span v-if="props.node._usageCounts.unit > 0" class="label neutral icon-unit" title="Units">{{
            props.node._usageCounts.unit
        }}</span>
        <span v-if="props.node._usageCounts.folder > 0" class="label neutral icon-folder" title="Folders">{{
            props.node._usageCounts.folder
        }}</span>
        <!-- <span v-if="props.node._usageCounts.folder > 0" class="label neutral icon-due-work" title="Assessment">3</span> -->
        <!-- <span v-if="props.node._usageCounts.course > 0" class="label neutral icon-badge" title="Badges">7</span> -->
    </button>
    <button
        :class="`button-action-option ${props.isViewing ? 'icon-close' : 'icon-curriculum-details'}`"
        :title="`View ${props.title}`"
        :disabled="props.isUpdating || props.isAdding"
        data-test-view
        @click.prevent="onViewToggleSelected"
    >
        Update {{ props.title }}
    </button>
</template>

<style lang="scss" scoped>
@import "./style/mcViewNode";
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
