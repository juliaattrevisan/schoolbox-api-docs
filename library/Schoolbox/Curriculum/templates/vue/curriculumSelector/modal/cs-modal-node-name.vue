<script setup lang="ts">
/**
 * A curriculum node to select to traverse further down the tree,
 * will also highlight any matches in the current search query when search is active
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import { useNodesStore } from "../store/csNodesStore";
import { useModalViewNodesStore } from "../store/csModalViewNodesStore";
import { useModalSearchStore } from "../store/csModalSearchStore";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // to gradually add more marginLeft to indicate node level depth
    title: string; // the already computed node title
    isActive: boolean; // to disable certain events when editing is taking place
}
const props = withDefaults(defineProps<Props>(), {
    node: () => getEmptyNode(),
    depth: 0,
    title: "",
    isActive: false,
});

const nodesStore = useNodesStore();
const modalViewNodesStore = useModalViewNodesStore();
const modalSearchStore = useModalSearchStore();

/**
 * To disable selection on certain situations
 * @returns {boolean} - whether the button is disabled or not
 */
const isDisabled = window.Vue.computed(() => {
    if (props.isActive || nodesStore.isLoading) {
        // while the node is being updated
        return true;
    }
    return false;
});

/**
 * To distinguish the parent node
 * @returns {boolean} - whether the node is a parent or not
 */
const isParentNode = window.Vue.computed(() => {
    if (props.depth === 0) {
        return true;
    }
    return false;
});

/**
 * To help distinguish those nodes that aren't selectable,
 * we disable and remove the default button colour
 * @returns {boolean} - whether the button is visible or not
 */
const isButton = window.Vue.computed(() => {
    if (modalSearchStore.isActive) {
        // when search is active, we can jump to the selected node
        return true;
    }
    if (isParentNode.value) {
        // is the parent node, which we don't
        return false;
    }
    if (props.node.children === null) {
        // the children haven't been loaded
        return false;
    }
    if (props.node.children?.length === 0) {
        // there's no children to display, so no point going further
        return false;
    }
    return true;
});

/**
 * Only use bold on the title when
 * @returns {string} - the font weight to use
 */
const fontWeight = window.Vue.computed(() => (isParentNode.value && !modalSearchStore.isActive ? "bold" : "normal"));

/**
 * traverse to the next Node level
 */
function onNameSelected(): void {
    if (modalSearchStore.isActive) {
        // reset the structure and move to the selected searched node
        void modalViewNodesStore.toId(props.node.id);
    } else {
        // traverse to the next node level
        void modalViewNodesStore.setNextSelected(props.node.id);
    }
}
</script>

<template>
    <show-matches
        v-if="isButton"
        tag="button"
        class="button-name"
        :title="`View ${props.title}`"
        :disabled="isDisabled"
        :text="props.title"
        :query="modalSearchStore.query"
        :is-searching="modalSearchStore.isActive"
        @click="onNameSelected"
    />
    <show-matches
        v-else
        tag="span"
        class="name"
        :text="props.title"
        :query="modalSearchStore.query"
        :is-searching="modalSearchStore.isActive"
    />
</template>

<style lang="scss">
.button-name {
    background: none;
    font-size: 1rem;
    margin: 0;
    min-height: 42px;
    min-width: 40px;
    padding-left: 0.25rem;
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-background);
    }
    &:disabled {
        opacity: 0.2;
    }
    @media (max-width: 700px) {
        font-size: 0.8rem;
        padding: 0.25rem;
    }
}

.name {
    color: var(--header-font-color);
    opacity: 1;
    padding: 0.5rem 0;
    font-size: 1rem;
    font-weight: v-bind(fontWeight);
    width: 100%;
}
</style>
