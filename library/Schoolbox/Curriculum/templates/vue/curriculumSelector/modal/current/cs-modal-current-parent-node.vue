<script setup lang="ts">
/**
 * Show a single node and any of its children
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import { useCurrentNodesStore } from "../../store/csCurrentNodesStore";
import csModalCurrentChildNode from "./cs-modal-current-child-node.vue";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // the depth of the node in the tree
}
const props = defineProps<Props>();

const store = useCurrentNodesStore();
const { title } = useNodeTitle(props.node);
const showChildren = props.node.children && props.node.children.length > 0; // To determine whether we need to display further child nodes within this node
const isExpanded = window.Vue.ref(store.getIsExpanded(props.node.id)); // will not be expanded if there are multiple parents, to avoid potential long scrolls in the modal

/**
 * Show the expand button if there's multiple top level parents, it's the parent node,
 * and when the node has children
 */
const showExpandButton = window.Vue.computed(() => {
    return store.hasMultipleParents && props.node.children && props.node.children.length > 0;
});

/**
 * Expand or collapse the children of the node
 */
function onExpandSelected(): void {
    isExpanded.value = !isExpanded.value; // toggle the expanded state
    store.setIsExpanded(props.node.id, isExpanded.value); // update the store with the new expanded state
}
</script>

<template>
    <li>
        <div class="node l-gap-xs l-pad-xs--vert">
            <div :class="{ 'l-flex l-flex--align-center l-gap-xs f-no-margins': showExpandButton }">
                <button
                    v-if="showExpandButton"
                    class="l-flex l-flex--center l-flex__item--0-shrink f-no-bkg"
                    :aria-label="isExpanded ? 'Collapse' : 'Expand'"
                    :title="isExpanded ? 'Collapse' : 'Expand'"
                    :data-test="`cc-report-table-node-expand-button-${props.node.id}`"
                    @click="onExpandSelected"
                >
                    <sbx-icon v-if="isExpanded" name="up-arrow" />
                    <sbx-icon v-else name="down-arrow" />
                </button>
                <button
                    v-if="showExpandButton"
                    class="node__name node__name--button f-no-margins"
                    @click="onExpandSelected"
                >
                    {{ title }}
                </button>
                <h3 v-else class="node__name node__name--title f-no-margins">{{ title }}</h3>
            </div>
        </div>
        <template v-if="showChildren">
            <ul>
                <cs-modal-current-child-node
                    v-for="viewNode in props.node.children"
                    :key="viewNode.id"
                    :node="viewNode"
                    :depth="props.depth + 1"
                    :is-expanded="isExpanded"
                />
            </ul>
        </template>
    </li>
</template>

<style lang="scss" scoped>
.node:not(:last-child) {
    border-bottom: 1px solid var(--sbx-color-border, var(--body-background)); /* don't add borders on the last nodes for better readability */
}

.checklist {
    flex: 2rem 0; /* ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it */
}

.node__name {
    font-size: 1rem;
    font-weight: bold;
    max-width: none; /* override the max-width set on .reveal-modal h3 */
    vertical-align: top; /* so the li bullet sits inline with the first line of a multiline label */
}

.node__name--button {
    background: none;
    &:hover {
        background: none;
    }
}

.node__name--title {
    color: var(--header-font-color);
    padding-inline-end: var(--spacing-s); /* to avoid the text sitting right on the component edge */
}

ul li ul {
    margin-inline-start: var(--spacing-xl);
}
</style>
