<script setup lang="ts">
/**
 * Show a single node and any of its children
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useUnitNodesStore } from "../../store/csUnitNodesStore";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // the depth of the node in the tree
}

const props = defineProps<Props>();

const store = useUnitNodesStore();

/**
 * To determine whether we need to display further child nodes within this node
 * @returns {boolean}
 */
const showChildren = window.Vue.computed(() => props.node.children && props.node.children.length > 0);

/**
 * If there's a code available, which means it can be removed from the selected nodes or
 * the code can be selected to open the curriculum details modal
 * @returns {boolean}
 */
const hasCode = window.Vue.computed(() => props.node.code && props.node.code !== "");

/**
 *
 * @returns {string}
 */
const fontWeight = window.Vue.computed(() => {
    if (props.depth === 0) return "bold";
    return "normal";
});

/**
 * When the code is selected, switch across to the curriculum details modal
 */
function onCodeSelected(): void {
    // store().selectedDetailsNodeId = props.node.id;
}

/**
 * Add/remove the node from the selected nodes
 */
function onNodeSelected(): void {}
</script>

<template>
    <li>
        <div class="node l-flex-row l-flex--justify-space l-flex--center l-flex--no-wrap l-pad-xs--vert">
            <div class="l-flex-row l-flex--no-wrap l-gap-xs">
                <a
                    v-if="hasCode"
                    class="node__code f-no-margins l-flex__item--0-shrink"
                    :href="`#${props.node.id}`"
                    @click.prevent="onCodeSelected"
                    >[{{ props.node.code }}]
                </a>
                <h3 class="node__name f-no-margins">{{ props.node.name }}</h3>
            </div>
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

.node__code,
.node__name {
    font-size: 1rem;
    font-weight: v-bind(fontWeight);
    max-width: none; /* override the max-width set on .reveal-modal h3 */
}

.node__name {
    padding-inline-end: var(--spacing-s); /* to avoid the text sitting right on the component edge */
}

ul li ul {
    margin-inline-start: var(--spacing-s);
}
</style>
