<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * will also highlight any matches in the current search query when search is active
 */

import { computed } from 'vue';
import { escapeRegExp } from '@js/util/jsUtils';
import { useNodesStore } from '../store/mcNodesStore';
import { useViewNodesStore } from './store/mcViewNodesStore';


const props = defineProps({
    /**
     * the assigned node to display
     */
    node: {
        type: Object,
        required: true,
        default() {
            return {
                id: '0',
                name: '-',
                children: [],
            }
        }
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
    isUpdatingOrAdding: {
        type: Boolean,
        required: true,
    },
});

const nodesStore = useNodesStore();
const viewNodesStore = useViewNodesStore();

/**
 * To disable selection on certain sitatuions
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => {
    if (props.isUpdatingOrAdding) { //while the node is being updated
        return true;
    } else if (nodesStore.isLoading) { //or while a load is in progress
        return true;
    } else {
        return false;
    }
});

/**
 * To distinguish the parent node
 *
 * @returns { Boolean }
 */
 const isParentNode = computed(() => {
    if (props.depth === 0) {
        return true;
    } else {
        return false;
    }
});

/**
 * To help distinguish those nodes that aren't selectable,
 * we disable and remove the default button colour
 *
 * @returns { Boolean }
 */
const isButton = computed(() => {
    if (isParentNode.value) { //is the parent node, which we don't
        return false;
    } else if (props.node.children?.length === 0) { //there's no children to display, so no point going further
        return false;
    } else if (viewNodesStore.isSearchActive) { //cant do any navigation while search is active
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
 const fontWeight = computed(() => isParentNode.value && !viewNodesStore.isSearchActive ? 'bold' : 'normal');

/**
 * traverse to the next nodel level
 */
 function onNameSelected () {
    viewNodesStore.setNextSelected(props.node.id);
}

/**
 * To highlight any search selections in the code / title
 *
 * @returns { HTML }
 */
const highlightTitleSearch = computed(() => {
    if (viewNodesStore.isSearchActive) {
        return highlightSearchInElement(props.title);
    } else {
        return props.title;
    }
});

/**
 * To highlight any search selections in the breadcrumbs
 *
 * @returns { HTML }
 */
const highlightBreadcrumbSearch = computed(() => {
    if (viewNodesStore.isSearchActive && props.node.breadcrumb) {
        return highlightSearchInElement(props.node.breadcrumb);
    } else {
        return '';
    }
});

/**
 * To highlight where the search string matches within the settings result
 * (?![^>]*>) is used to check we are not inside an element node
 *
 * @params { String } the string to find all instanced of the current search string in
 *
 * @returns { String } string for v-html with <mark></mark> wrapped around each found result
 */
function highlightSearchInElement (toHighlight) {
    let highlighted = toHighlight;
    viewNodesStore.queryWordList.forEach((wordInSearch) => {
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^mark>]*mark>)`, 'ig'), (match) => {
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
        @keyup.enter="onNameSelected"
    >
        {{ props.title }}
    </button>
    <div
        v-else
        class="name"
    >
        <span v-html="highlightTitleSearch"></span>
        <aside v-if="viewNodesStore.isSearchActive" v-html="highlightBreadcrumbSearch"></aside>
    </div>

</template>

<style lang="scss" scoped>

@import './style/mcViewNode';

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
    width: 100%;
}

aside {
    font-size: 0.725rem;
    font-style: italic;
}

</style>