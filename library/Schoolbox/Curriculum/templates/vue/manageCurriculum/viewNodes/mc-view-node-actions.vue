<script setup lang="ts">
/**
 * To edit an existing node or add a new subsection to a node
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { hasUsage } from "@schoolboxCurriculum/js/nodeUtils";
import { useNodeDetailsStore } from "../activeNode/store/mcNodeDetailsStore";
import { useAddSubsectionStore } from "../activeNode/store/mcAddSubsectionStore";
import { useUpdateNodeStore } from "../activeNode/store/mcUpdateNodeStore";
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
const addSubsectionStore = useAddSubsectionStore();
const updateNodeStore = useUpdateNodeStore();

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

/**
 * to open up or close the add subsection modal
 */
function onAddSubsectionToggleSelected() {
    if (props.isAdding) {
        addSubsectionStore.cancel();
    } else {
        addSubsectionStore.prompt(props.node);
    }
}

/**
 * to open up or close the update modal
 */
function onUpdateToggleSelected() {
    if (props.isUpdating) {
        updateNodeStore.cancel();
    } else {
        updateNodeStore.prompt(props.node);
    }
}
</script>

<template>
    <mc-view-node-actions-usage
        v-if="hasUsage(props.node)"
        :node="props.node"
        :title="props.title"
        :is-updating="props.isUpdating"
        :is-adding="props.isAdding"
        @selected="onViewToggleSelected"
    />
    <button
        :class="`button-action-option ${props.isViewing ? 'icon-close' : 'icon-curriculum-details'}`"
        :title="`View ${props.title}`"
        :disabled="props.isUpdating || props.isAdding"
        data-test-view
        @click.prevent="onViewToggleSelected"
    >
        Update {{ props.title }}
    </button>
    <button
        :class="`button-action-option ${props.isUpdating ? 'icon-close' : 'icon-modify'}`"
        :title="`Update ${props.title}`"
        :disabled="props.isViewing || props.isAdding"
        data-test-update
        @click.prevent="onUpdateToggleSelected"
    >
        Update {{ props.title }}
    </button>
    <button
        :class="`button-action-option ${props.isAdding ? 'icon-close' : 'icon-add'}`"
        :title="`Add subsection to ${props.title}`"
        :disabled="props.isViewing || props.isUpdating"
        data-test-add
        @click.prevent="onAddSubsectionToggleSelected"
    >
        Add subsection to {{ props.title }}
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
</style>
