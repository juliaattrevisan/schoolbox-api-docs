<script setup lang="ts">
/**
 * Shows the activity count types when available and can be selected
 * to reveal the node description and connected material
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useNodeDetailsStore } from "../activeNode/store/mcNodeDetailsStore";

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
        <span v-if="props.node._usageCounts?.course > 0" class="label neutral icon-course-builder" title="Courses">{{
            props.node._usageCounts.course
        }}</span>
        <span v-if="props.node._usageCounts?.unit > 0" class="label neutral icon-unit" title="Units">{{
            props.node._usageCounts.unit
        }}</span>
        <!-- <span v-if="props.node._usageCounts?.assessment > 0" class="label neutral icon-due-work" title="Assessment">3</span> -->
        <!-- <span v-if="props.node._usageCounts?.badge > 0" class="label neutral icon-badge" title="Badges">7</span> -->
        <span v-if="props.node._usageCounts?.folder > 0" class="label neutral icon-folder" title="Folders">{{
            props.node._usageCounts.folder
        }}</span>
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
