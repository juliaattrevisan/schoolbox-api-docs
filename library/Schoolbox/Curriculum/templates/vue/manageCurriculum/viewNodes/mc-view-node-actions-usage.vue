<script setup lang="ts">
/**
 * To display the usages linked to the node
 */

import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    node: Node; // the assigned node to display
    title: string; // the already computed node title
    isUpdating: boolean; // to disable certain events when updating the node
    isAdding: boolean; // to disable certain events when adding a node
}

const props = defineProps<Props>();
const emit = defineEmits(["selected"]);

/**
 * Dispatch to mc-view-node-actions to respond accordingly
 */
function onSelected() {
    emit("selected");
}
</script>

<template>
    <button
        class="usage-counts"
        :title="`View ${props.title}`"
        :disabled="props.isUpdating || props.isAdding"
        @click.prevent="onSelected"
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
</template>

<style lang="scss" scoped>
@import "./style/mcViewNode";
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
