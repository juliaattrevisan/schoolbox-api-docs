<script setup lang="ts">
/**
 * Show a single node and any of its children
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import { generateUUID } from "@js/util/jsUtils";
import { useModalSelectedNodesStore } from "../../store/csModalSelectedNodesStore";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // the depth of the node in the tree
}

const props = defineProps<Props>();

const modalSelectedNodesStore = useModalSelectedNodesStore();
const { title } = useNodeTitle(props.node);
const extraId = generateUUID(); // to ensure the input/label id is unique
const showChildren = props.node.children && props.node.children.length > 0; // To determine whether we need to display further child nodes within this node
const hasCode = props.node.code && props.node.code !== ""; // If there's a code, it can be selected
const fontWeight = props.depth === 0 ? "bold" : "normal"; // so the top most nodes are bold

/**
 * Update the status of the checkbox based on whether it's selected or not
 * @returns {boolean} - whether the checkbox is checked or not
 */
const isChecked = window.Vue.computed(() => {
    return modalSelectedNodesStore.isSelected(props.node);
});

/**
 * add or remove based on the current status
 * @param $isChecked - whether the checkbox is checked or not
 */
function onChange($isChecked: boolean): void {
    modalSelectedNodesStore.switchNode(props.node, $isChecked);
}
</script>

<template>
    <li>
        <div class="node l-pad-xs--vert">
            <div v-if="hasCode" class="checklist l-gap-xs">
                <input
                    :id="`checkbox-${props.node.id}__${extraId}`"
                    type="checkbox"
                    :value="props.node.id"
                    :checked="isChecked"
                    @change="onChange(($event.target as HTMLInputElement).checked)"
                />
                <label
                    class="node__name f-no-margins"
                    :for="`checkbox-${props.node.id}__${extraId}`"
                    :data-test="`cs-modal-unit-node-checkbox-label-${props.node.id}`"
                >
                    {{ title }}
                </label>
            </div>
            <h3 v-else class="node__name f-no-margins">{{ title }}</h3>
        </div>
        <template v-if="showChildren">
            <ul>
                <cs-modal-unit-node
                    v-for="viewNode in props.node.children"
                    :key="viewNode.id"
                    :node="viewNode"
                    :depth="props.depth + 1"
                />
            </ul>
        </template>
    </li>
</template>

<style scoped>
.node:not(:last-child) {
    border-bottom: 1px solid var(--body-background);
}

.checklist {
    flex: 2rem 0; /* ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it */
}

.node__name {
    font-size: 1rem;
    font-weight: v-bind(fontWeight);
    max-width: none; /* override the max-width set on .reveal-modal h3 */
    vertical-align: top; /* so the li bullet sits inline with the first line of a multiline label */
}

.node__name {
    padding-inline-end: var(--spacing-s); /* to avoid the text sitting right on the component edge */
}

ul li ul {
    margin-inline-start: var(--spacing-s);
}
</style>
