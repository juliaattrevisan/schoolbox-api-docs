<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * will also highlight any matches in the current search query when search is active
 */

import { computed } from 'vue';
import { escapeRegExp } from '@js/util/jsUtils';
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

const viewNodesStore = useViewNodesStore();


/**
 * To help distinguish those nodes that aren't selectable,
 * we disable and remove the default button colour
 *
 * @returns { Boolean }
 */
const notButton = computed(() => {
    if (props.depth === 0) { //we can't go back any further
        return true;
    } else if (viewNodesStore.isSearchActive) { //cant do any navigation while search is active
        return true;
    } else {
        return false;
    }
});

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
    <div
        v-if="notButton"
        class="name"
        :style="{ opacity: props.node.enabled ? '1' : '0.3' }"
    >
        <span v-html="highlightTitleSearch"></span>
        <aside v-if="viewNodesStore.isSearchActive" v-html="highlightBreadcrumbSearch"></aside>
    </div>
    <button
        v-else
        :class="`button-name`"
        :style="{ opacity: props.node.enabled ? '1' : '0.3' }"
        :title="`Expand ${props.title}`"
        :disabled="props.isUpdatingOrAdding"
        @click.prevent="onNameSelected"
        @keyup.enter="onNameSelected"
    >
        {{ props.title }}
    </button>
</template>

<style lang="scss" scoped>

@import './style/mcViewNode';

.name {
    color: var(--body-foreground-h1);
    opacity: 1;
    font-size: 1rem;
    font-weight: bold;
    width: 100%;
}

aside {
    font-size: 0.725rem;
    font-style: italic;
}

.button-name {
    @extend .button;
    padding-left: 0.25rem;
    transition: background 0.2s;
    width: 100%;
}

</style>