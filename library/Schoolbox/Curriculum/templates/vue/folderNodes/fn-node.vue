<script setup lang="ts">
/**
 * Show a single node and any of its children
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore } from "./store/folderNodesStore";

interface Props {
    node: Node; // the assigned node to display
    folderId: number; // The folder id to display the nodes for, primarily used to reference the appropriate store
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();

const store = useStore(props.folderId);

/**
 * to gradually add more marginLeft to indicate node level depth
 * @returns {number} - the calculated marginLeft value
 */
const marginLeft = window.Vue.computed(() => `calc(${props.depth * 1}rem)`);

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
 * Whether the checkbox and actions should be displayed, shouldn't be if the user is read only
 * or we are at the root level node, or if the search is active
 * @returns { boolean }
 */
const showRemove = window.Vue.computed(() => hasCode.value && store().selectedNodeIds.includes(props.node.id));

/**
 * When the code is selected, open the curriculum details modal
 */
function onCodeSelected() {
    store().selectedDetailsNodeId = props.node.id;
}

/**
 * Remove the node from the selected nodes
 */
function onRemoveSelected() {
    if (store().isProcessing) return;
    void store().removeNode(props.node);
}
</script>

<template>
    <div>
        <div
            class="node l-flex-row l-flex--justify-space l-flex--align-center l-flex--no-wrap"
            :style="{ marginLeft: marginLeft }"
        >
            <div class="l-flex-row l-flex--no-wrap l-gap-xs">
                <a v-if="hasCode" class="f-no-margins" :href="`#${props.node.id}`" @click.prevent="onCodeSelected"
                    >[{{ props.node.code }}]
                </a>
                <h3 class="f-no-margins">{{ props.node.name }}</h3>
            </div>
            <button
                v-if="showRemove"
                class="remove-node f-no-icon-button icon-close l-flex-row l-flex--center"
                :aria-label="`Remove [${props.node.code}] ${props.node.name} from selected nodes`"
                :title="`Remove [${props.node.code}] ${props.node.name} from selected nodes`"
                @click.prevent="onRemoveSelected"
            ></button>
        </div>
        <template v-if="showChildren">
            <transition-group name="list" tag="div">
                <fn-node
                    v-for="viewNode in props.node.children"
                    :key="viewNode.id"
                    :node="viewNode"
                    :folder-id="props.folderId"
                    :depth="props.depth + 1"
                />
            </transition-group>
        </template>
    </div>
</template>

<style scoped>
.node {
    min-height: 2.5rem;
}

.node:not(:last-child) {
    border-bottom: 1px solid var(--body-background);
}

.list-enter-active,
.list-leave-active {
    transition: opacity 0.5s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
}
</style>
