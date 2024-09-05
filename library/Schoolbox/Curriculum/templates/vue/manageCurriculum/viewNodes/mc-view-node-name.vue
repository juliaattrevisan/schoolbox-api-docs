<script setup lang="ts">
/**
 * A curriculum node to select to traverse further down the tree,
 * will also highlight any matches in the current search query when search is active
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useNodesStore } from "../store/mcNodesStore";
import { useViewNodesStore } from "./store/mcViewNodesStore";
import { useSearchStore } from "../store/mcSearchStore";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // to gradually add more marginLeft to indicate node level depth
    title: string; // the already computed node title
    isActive: boolean; // to disable certain events when editing is taking place
}

const props = withDefaults(defineProps<Props>(), {
    depth: 0,
    title: "-",
    isActive: false,
});

const nodesStore = useNodesStore();
const viewNodesStore = useViewNodesStore();
const searchStore = useSearchStore();

/**
 * To disable selection on certain sitatuions
 * @returns { boolean }
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
 * @returns { boolean }
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
 * @returns { boolean }
 */
const isButton = window.Vue.computed(() => {
    if (searchStore.isActive) {
        // when search is active, we can jump to the selected node
        return true;
    }
    if (isParentNode.value) {
        // is the parent node, which we can't select
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
 * @returns { string }
 */
const fontWeight = window.Vue.computed(() => (isParentNode.value && !searchStore.isActive ? "bold" : "normal"));

/**
 * reset the structure to that node or traverse to the next node level
 */
async function onNameSelected(): Promise<void> {
    if (searchStore.isActive) {
        // reset the structure and move to the selected searched node
        await viewNodesStore.toId(props.node.id);
    } else {
        // traverse to the next node level
        await viewNodesStore.setNextSelected(props.node.id);
    }
}
</script>

<template>
    <div class="name-container">
        <show-matches
            v-if="isButton"
            tag="button"
            class="button-name"
            :title="`Expand ${props.title}`"
            :disabled="isDisabled"
            :text="props.title"
            :query="searchStore.query"
            :is-searching="searchStore.isActive"
            @click="onNameSelected"
        />
        <show-matches
            v-else
            tag="span"
            class="name"
            :text="props.title"
            :query="searchStore.query"
            :is-searching="searchStore.isActive"
        />
    </div>
</template>

<style lang="scss">
.name-container {
    width: 100%;
}

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
    font-size: 1rem;
    font-weight: v-bind(fontWeight);
    line-height: 2.5;
    width: 100%;
}
</style>
