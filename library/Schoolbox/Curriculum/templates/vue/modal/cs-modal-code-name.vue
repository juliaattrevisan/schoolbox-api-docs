<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * will also highlight any matches in the current search query when search is active
 */

import { computed } from 'vue';
// import { escapeRegExp } from '@js/util/jsUtils';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';
// import { useSearchStore } from '../store/mcSearchStore';


const props = defineProps({
    /**
     * the assigned node to display
     */
    code: {
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
});

const viewNodesStore = useModalActiveCodesStore();
// const searchStore = useSearchStore();


/**
 * To help distinguish those nodes that aren't selectable,
 * we disable and remove the default button colour
 *
 * @returns { Boolean }
 */
const notButton = computed(() => {
    if (props.depth === 0) { //we can't go back any further
        return true;
    // } else if (searchStore.isActive) { //cant do any navigation while search is active
    //     return true;
    } else {
        return false;
    }
});

/**
 * traverse to the next nodel level
 */
function onNameSelected () {
    viewNodesStore.setNextSelected(props.code.id);
}

/**
 * To highlight any search selections in the code / title
 *
 * @returns { HTML }
 */
const highlightTitleSearch = computed(() => {
    // if (searchStore.isActive) {
    //     return highlightSearchInElement(props.title);
    // } else {
    //     return props.title;
    // }
    return props.title;
});

/**
 * To highlight any search selections in the breadcrumbs
 *
 * @returns { HTML }
 */
// const highlightBreadcrumbSearch = computed(() => {
//     if (searchStore.isActive && props.code.breadcrumb) {
//         return highlightSearchInElement(props.code.breadcrumb);
//     } else {
//         return '';
//     }
// });

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
    // viewNodesStore.queryWordList.forEach((wordInSearch) => {
    //     highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^mark>]*mark>)`, 'ig'), (match) => {
    //         return `<mark>${match}</mark>`;
    //     });
    // });
    return highlighted;
}

</script>

<template>
    <div
        v-if="notButton"
        :class="`name`"
    >
        <span v-html="highlightTitleSearch"></span>
        <!-- <aside v-if="searchStore.isActive" v-html="highlightBreadcrumbSearch"></aside> -->
    </div>
    <button
        v-else
        :class="`button-name`"
        :title="`Expand ${props.title}`"
        @click.prevent="onNameSelected"
        @keyup.enter="onNameSelected"
    >
        {{ props.title }}
    </button>
</template>

<style lang="scss" scoped>

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

.button {
    background: none;
    font-size: 1rem;
    margin: 0;
    min-height: 42px;
    min-width: 40px;
    text-align: left;
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

.button-name {
    @extend .button;
    padding-left: 0.25rem;
    transition: background 0.2s;
    width: 100%;
}

</style>