<script setup lang="ts">
/**
 * To be able to enable/disable or delete a node
 */

import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/selectedNodes/store/mcSelectedNodesStore";
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    node: Node; // the assigned node to display
    isActive: boolean; // to disable certain events when editing is taking place
}

const props = defineProps<Props>();
const selectedNodesStore = useSelectedNodesStore();

/**
 * if the node is a child of one that's selected it should be disabled
 * @returns { boolean } whether the checkbox should be disabled
 */
const isDisabled = window.Vue.computed(() => {
    return selectedNodesStore.childNodesIds.includes(props.node.id) || props.isActive;
});

/**
 * Update the status of the checkbox based on whether it's selected or not
 * @returns { boolean }
 */
const isChecked = window.Vue.computed(() => {
    return (
        selectedNodesStore.nodesIds.includes(props.node.id) || selectedNodesStore.childNodesIds.includes(props.node.id)
    );
});

/**
 * add or remove based on the current status
 * @param isCheckedChange - whether the Node is selected or not
 */
function onChange(isCheckedChange: boolean): void {
    selectedNodesStore.switchNode(props.node, isCheckedChange);
}
</script>

<template>
    <div class="checklist">
        <input
            :id="`checkbox-${props.node.id}`"
            type="checkbox"
            :value="props.node.id"
            :disabled="isDisabled"
            :checked="isChecked"
            @change="onChange(($event.target as HTMLInputElement).checked)"
        />
        <label :for="`checkbox-${props.node.id}`"></label>
    </div>
</template>

<style lang="scss" scoped>
.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    padding-top: 0.5rem;
}
</style>
