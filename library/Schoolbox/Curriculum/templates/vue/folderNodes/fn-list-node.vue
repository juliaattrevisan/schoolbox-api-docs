<script setup lang="ts">
/**
 * Show a single node and any of its children
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore } from "./store/folderNodesStore";

interface Props {
    node: Node; // the assigned node to display
    folderId: number; // The folder id to display the nodes for, primarily used to reference the appropriate store
    componentInstanceId: number; // The component instance id to display the nodes on
    isSmall?: boolean; // Whether the list should be displayed in a small format
}

const props = defineProps<Props>();

const store = useStore(props.folderId, props.componentInstanceId);

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
 * Shrink the font size if props.isSmall is true
 * @returns {string}
 */
const fontSize = window.Vue.computed(() => {
    if (props.isSmall) return "0.8125rem";
    return "1rem";
});

/**
 * Tighten up the margins down the node tree when props.isSmall is true
 * @returns {string}
 */
const marginStart = window.Vue.computed(() => {
    if (props.isSmall) return "var(--spacing-s)";
    return "var(--spacing-m)";
});

/**
 * Whether the checkbox and actions should be displayed, shouldn't be if the user is read only
 * or we are at the root level node, or if the search is active
 * @returns { boolean }
 */
const showRemove = window.Vue.computed(
    () => !store().isReadOnly && hasCode.value && store().selectedNodeIds.includes(props.node.id),
);

/**
 * When the code is selected, open the curriculum details modal
 */
function onCodeSelected(): void {
    store().selectedDetailsNodeId = props.node.id;
}

/**
 * Remove the node from the selected nodes
 */
function onRemoveSelected(): void {
    if (store().isProcessing) return;
    void store().removeNode(props.node);
}
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
            <button
                v-if="showRemove"
                class="node__remove f-no-icon-button icon-close l-flex-row l-flex--center l-flex__item--0-shrink"
                :aria-label="`Remove [${props.node.code}] ${props.node.name}`"
                :title="`Remove [${props.node.code}] ${props.node.name}`"
                @click.prevent="onRemoveSelected"
            ></button>
        </div>
        <template v-if="showChildren">
            <ul>
                <fn-list-node
                    v-for="viewNode in props.node.children"
                    :key="viewNode.id"
                    :node="viewNode"
                    :folder-id="props.folderId"
                    :component-instance-id="props.componentInstanceId"
                    :is-small="props.isSmall"
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
    font-size: v-bind(fontSize);
}

.node__name {
    padding-inline-end: var(--spacing-s); /* to avoid the text sitting right on the component edge */
}

.node__remove {
    max-height: 2.5rem; /* to ensure the button doesn't stretch too much */
    width: 2.5rem;
}

ul li ul {
    margin-inline-start: v-bind(marginStart);
}
@media print {
    a {
        color: #000 !important;
        text-decoration: none !important;
    }
}
</style>
