<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the edit button
 */

import { computed } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useVisibleNodesStore } from './store/mcVisibleNodesStore';
import { useEditNodeStore } from './store/mcEditNodeStore';
import { escapeRegExp } from '@js/util/jsUtils';


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
     * to gradually add more paddingLeft to indicate node level depth
     */
    depth: {
        type: Number,
        required: true,
    },
});

const store = useStore();
const visibleNodesStore = useVisibleNodesStore();
const editNodeStore = useEditNodeStore();

/**
 * to gradually and more paddingLeft to indicate node level depth
 *
 * @returns { Number }
 */
const paddingLeft = computed(() => `calc(${props.depth * 2}rem)`);

/**
 * If there's a code available, which means it can be added to the name
 *
 * @returns { Boolean }
 */
const hasCode = computed(() => props.node.code && props.node.code !== '');

/**
 * To help distinguish those nodes that aren't selectable,
 * we disable and remove the default button colour
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => {
    if (props.depth === 0 && !visibleNodesStore.isSearchActive) {
        return true;
    } else if (visibleNodesStore.isOnLast) { //cant select any further down from the MAX_DEPTH
        return true;
    } else {
        return false;
    }
});

/**
 * Only show the selected node and its direct children when we are on the fourth node
 * This is so we don't flood the DOM with very large Curriculum renders
 *
 * @returns { Boolean }
 */
const isVisible = computed(() => props.depth <= 1 || visibleNodesStore.isOnLast);

/**
 * to open up the edit modal
 */
function onEditSelected () {
    editNodeStore.set(props.node);
}

/**
 * traverse to the next nodel level as an alternative to
 * using the selects in mc-header-select
 */
function onNameSelected () {
    editNodeStore.unset();
    visibleNodesStore.setNextSelected(props.node.id);
}

/**
 * To determine whether we need to display further child nodes within for this node
 *
 * @returns { Boolean }
 */
const hasChildren = computed(() => props.node.children && props.node.children.length > 0);

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => hasCode.value ? `[${props.node.code}] ${props.node.name}` : `${props.node.name}`);

/**
 * To highlight any search selections in the code / title
 *
 * @returns { HTML }
 */
const highlightTitleSearch = computed(() => {
    if (visibleNodesStore.isSearchActive) {
        return highlightSearchInElement(title.value);
    } else {
        return title.value;
    }
});

/**
 * To highlight any search selections in the breadcrumbs
 *
 * @returns { HTML }
 */
const highlightBreadcrumbSearch = computed(() => {
    if (visibleNodesStore.isSearchActive && props.node.breadcrumb) {
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
    visibleNodesStore.queryWordList.forEach((wordInSearch) => {
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^mark>]*mark>)`, 'ig'), (match) => {
            return `<mark>${match}</mark>`;
        });
    });
    return highlighted;
}

/**
 * Add some styling to help visually clarify the tree structure
 *
 * @returns { String }
 */
const containerClass = computed(() => {
    if (visibleNodesStore.isSearchActive) { //don't style when using search, as all the nodes are on one level
        return '';
    } else if (props.depth === 0) {
        return 'parent-container';
    } else {
        '';
    }
});

</script>

<template>
    <li v-if="isVisible" :class="containerClass">
        <div class="buttons-container">
            <button
                v-if="!store.isReadOnly"
                class="edit-button icon-modify"
                :title="`Edit ${title}`"
                @click.prevent="onEditSelected"
                @keyup.enter="onEditSelected"
            >

            </button>
            <button
                :class="`name-button`"
                :disabled="isDisabled"
                :title="`Expand ${title}`"
                @click.prevent="onNameSelected"
                @keyup.enter="onNameSelected"
            >
                <span v-html="highlightTitleSearch"></span>
                <aside v-if="visibleNodesStore.isSearchActive" v-html="highlightBreadcrumbSearch"></aside>
            </button>
        </div>
        <ul v-if="hasChildren">
            <mc-view-node
                v-for="node in props.node.children"
                :key="node.id"
                :node="node"
                :depth="props.depth+1"
            />
        </ul>
   </li>
</template>

<style lang="scss" scoped>

.parent-container {
    > button span {
        font-size: 1.0625rem;
        font-weight: bold;
    }
}

.buttons-container {
    display: flex;
    margin: 0 0 0 v-bind(paddingLeft);
}

.button {
    background: none;
    font-size: 1rem;
    margin: 0;
    padding: 0.5rem;
    text-align: left;
    &:hover {
        background: var(--content-ui-background);
    }
    @media (max-width: 700px) {
        font-size: 0.8rem;
        padding: 0.25rem;
    }
}

.edit-button {
    @extend .button;
    color: var(--content-ui-foreground);
    // opacity: 0.7;

}

.name-button {
    @extend .button;
    transition: background 0.2s;
    width: 100%;
    &:disabled {
        color: #222222;
        opacity: 1;
    }
}

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
}

ul {
    list-style: none; //don't use any dots
    margin: 0; //and spacing is controlled by props.depth
}

aside {
    font-size: 0.725rem;
    font-style: italic;
}

</style>