<script setup lang="ts">
/**
 * A curriculum node to select to traverse further down the tree,
 * will also highlight any matches in the current search query when search is active
 */

import { computed } from "vue";
import { escapeRegExp } from "@js/util/jsUtils";
import { useNodesStore } from "../store/mcNodesStore";
import { useViewNodesStore } from "./store/mcViewNodesStore";
import { useSearchStore } from "../store/mcSearchStore";

const props = defineProps({
    /**
     * the assigned node to display
     */
    node: {
        type: Object,
        required: true,
        default() {
            return {
                id: "0",
                name: "-",
                children: [],
            };
        },
    },
    /**
     * to gradually add more marginLeft to indicate node level depth
     */
    depth: {
        type: Number,
        required: true,
    },
    /**
     * the already computed node title
     */
    title: {
        type: String,
        required: true,
    },
    /**
     * to disable certain events when editing is taking place
     */
    isActive: {
        type: Boolean,
        required: true,
    },
});

const nodesStore = useNodesStore();
const viewNodesStore = useViewNodesStore();
const searchStore = useSearchStore();

/**
 * To disable selection on certain sitatuions
 *
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
 *
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
 *
 * @returns { boolean }
 */
const isButton = window.Vue.computed(() => {
    if (searchStore.isActive) {
        //when search is active, we can jump to the selected node
        return true;
    } else if (isParentNode.value) {
        //is the parent node, which we can't select
        return false;
    } else if (props.node.children === null) {
        //the children haven't been loaded
        return false;
    } else if (props.node.children?.length === 0) {
        //there's no children to display, so no point going further
        return false;
    } else {
        return true;
    }
});

/**
 * Only use bold on the title when
 *
 * @returns { string }
 */
const fontWeight = window.Vue.computed(() => (isParentNode.value && !searchStore.isActive ? "bold" : "normal"));

/**
 * reset the structure to that node or traverse to the next node level
 */
function onNameSelected() {
    if (searchStore.isActive) {
        // reset the structure and move to the selected searched node
        viewNodesStore.toId(props.node.id);
    } else {
        // traverse to the next node level
        viewNodesStore.setNextSelected(props.node.id);
    }
}

/**
 * To highlight any search selections in the code / title
 *
 * @returns { string } - the highlighted title
 */
const highlightTitleSearch = window.Vue.computed(() => {
    if (searchStore.isActive) {
        return highlightSearchInElement(props.title);
    }
    return props.title;
});

/**
 * To highlight where the search string matches within the settings result
 * (?![^>]*>) is used to check we are not inside an element node
 *
 * @param { string } toHighlight - the string to find all instanced of the current search string in
 * @returns { string } string for v-html with <mark></mark> wrapped around each found result
 */
function highlightSearchInElement(toHighlight: string) {
    let highlighted = toHighlight;
    searchStore.queryWordList.forEach((wordInSearch) => {
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^mark>]*mark>)`, "ig"), (match) => {
            return `<mark>${match}</mark>`;
        });
    });
    return highlighted;
}
</script>

<template>
    <button
        v-if="isButton"
        class="button-name"
        :title="`Expand ${props.title}`"
        :disabled="isDisabled"
        @click.prevent="onNameSelected"
    >
        <span v-html="highlightTitleSearch"></span>
    </button>
    <div v-else class="name">
        <span v-html="highlightTitleSearch"></span>
    </div>
</template>

<style lang="scss" scoped>
@import "./style/mcViewNode";

.button-name {
    @extend .button;
    padding-left: 0.25rem;
    transition: background 0.2s;
    width: 100%;
}

.name {
    color: var(--body-foreground-h1);
    opacity: 1;
    font-size: 1rem;
    font-weight: v-bind(fontWeight);
    line-height: 2.5;
    width: 100%;
}
</style>
