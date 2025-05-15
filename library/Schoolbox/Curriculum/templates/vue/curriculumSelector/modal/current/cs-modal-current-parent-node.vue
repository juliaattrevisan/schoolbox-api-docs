<script setup lang="ts">
/**
 * Show a single node and any of its children
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import { generateUUID } from "@js/util/jsUtils";
import { useCurrentNodesStore } from "../../store/csCurrentNodesStore";
import { useModalSelectedNodesStore } from "../../store/csModalSelectedNodesStore";
import csModalCurrentChildNode from "./cs-modal-current-child-node.vue";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // the depth of the node in the tree
    isExpanded: boolean; // whether the node is expanded or not
}
const props = defineProps<Props>();

const store = useCurrentNodesStore();
const modalSelectedNodesStore = useModalSelectedNodesStore();
const { title } = useNodeTitle(props.node);
const extraId = generateUUID(); // to ensure the input/label id is unique
const showChildren = props.node.children && props.node.children.length > 0; // To determine whether we need to display further child nodes within this node
const hasCode = props.node.code && props.node.code !== ""; // If there's a code, it can be selected
const isNodeExpanded = window.Vue.ref(props.isExpanded); // to track whether the node is expanded or not

/**
 * Show the expand button there's multiple top level parents, it's the parent node,
 * and when the node has children
 */
const showExpandButton = window.Vue.computed(() => {
    return store.hasMultipleParents && props.node.children && props.node.children.length > 0;
});

/**
 * Expand or collapse the children of the node
 */
function onExpandSelected(): void {
    isNodeExpanded.value = !isNodeExpanded.value; // toggle the expanded state
}

/**
 * Update the status of the checkbox based on whether it's selected or not
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
    <li v-if="props.isExpanded">
        <div class="node l-gap-xs l-pad-xs--vert">
            <div :class="{ 'l-flex l-flex--align-center l-gap-xs f-no-margins': showExpandButton }">
                <button
                    v-if="showExpandButton"
                    class="l-flex l-flex--center l-flex__item--0-shrink f-no-bkg"
                    :aria-label="isNodeExpanded ? 'Collapse' : 'Expand'"
                    :title="isNodeExpanded ? 'Collapse' : 'Expand'"
                    :data-test="`cc-report-table-node-expand-button-${props.node.id}`"
                    @click="onExpandSelected"
                >
                    <sbx-icon v-if="isNodeExpanded" name="up-arrow" />
                    <sbx-icon v-else name="down-arrow" />
                </button>
                <div v-if="hasCode" class="checklist l-gap-xs l-pad-xs--vert">
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
                        :data-test="`cs-modal-current-node-checkbox-label-${props.node.id}`"
                    >
                        {{ title }}
                    </label>
                </div>
                <h3 v-else class="node__name f-no-margins" @click="onExpandSelected">{{ title }}</h3>
            </div>
        </div>
        <template v-if="showChildren">
            <ul>
                <cs-modal-current-child-node
                    v-for="viewNode in props.node.children"
                    :key="viewNode.id"
                    :node="viewNode"
                    :depth="props.depth + 1"
                    :is-expanded="isNodeExpanded"
                />
            </ul>
        </template>
    </li>
</template>

<style scoped>
.node:not(:last-child) {
    border-bottom: 1px solid var(--body-background); /* don't add borders on the last nodes for better readability */
}

.checklist {
    flex: 2rem 0; /* ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it */
}

.node__name {
    color: var(--header-font-color);
    font-size: 1rem;
    font-weight: bold;
    max-width: none; /* override the max-width set on .reveal-modal h3 */
    vertical-align: top; /* so the li bullet sits inline with the first line of a multiline label */
}

.node__name {
    padding-inline-end: var(--spacing-s); /* to avoid the text sitting right on the component edge */
}

ul li ul {
    margin-inline-start: var(--spacing-xl);
}
</style>
