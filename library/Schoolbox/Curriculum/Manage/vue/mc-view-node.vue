<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the edit button
 */

import { computed } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useViewNodesStore } from './store/mcViewNodesStore';
import { useActiveNodeStore } from './activeNode/store/mcActiveNodeStore';
import { useDeleteNodeStore } from './activeNode/store/mcDeleteNodeStore';
import { useUpdateNodeStore } from './activeNode/store/mcUpdateNodeStore';
import { escapeRegExp } from '@js/util/jsUtils';
import McUpdateNode from './activeNode/mc-update-node.vue';


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
const viewNodesStore = useViewNodesStore();
const activeNodeStore = useActiveNodeStore();
const deleteNodeStore = useDeleteNodeStore();
const updateNodeStore = useUpdateNodeStore();

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
    if (viewNodesStore.currentDepth === 0 && props.depth === 0) { //we can't to back any further
        return true;
    } else if (viewNodesStore.isSearchActive) { //cant do any navigation while search is active
        return true;
    } else if (viewNodesStore.isOnLast) { //cant select any further down from the MAX_DEPTH
        return true;
    } else {
        return false;
    }
});

/**
 * Only show the selected node and its direct children when we are on the sixth node
 * This is so we don't flood the DOM with very large Curriculum renders
 *
 * @returns { Boolean }
 */
const isVisible = computed(() => props.depth < 2 || viewNodesStore.isOnLast);

/**
 * If this is the active node
 *
 * @returns { Boolean }
 */
const isUpdating = computed(() => updateNodeStore.isModalOpen && activeNodeStore.node.id === props.node.id);

/**
 * traverse to the next nodel level as an alternative to
 * using the selects in mc-header-select or if the top parent is selected move back one level
 */
function onNameSelected () {
    activeNodeStore.unset();
    if (props.depth === 0) {
        viewNodesStore.setSelected(viewNodesStore.currentDepth, 0);
    } else {
        viewNodesStore.setNextSelected(props.node.id);
    }
}

/**
 * to prompt the nodes removal
 */
function onDeleteSelected () {
    deleteNodeStore.prompt(props.node);
}

/**
 * to open up or close the update modal
 */
function onUpdateToggleSelected () {
    if (isUpdating.value) {
        updateNodeStore.cancel();
    } else {
        updateNodeStore.prompt(props.node);
    }
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
    if (viewNodesStore.isSearchActive) {
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

/**
 * Add some styling to help visually clarify the tree structure
 *
 * @returns { String }
 */
const containerClass = computed(() => {
    if (viewNodesStore.isSearchActive) { //don't style when using search, as all the nodes are on one level
        return '';
    } else if (props.depth === 0) {
        return 'parent-container';
    } else {
        '';
    }
});

</script>

<template>
    <li v-if="isVisible" :class="`container ${containerClass}`">
        <div class="buttons-container">
            <div class="checklist">
                <input
                    :id="`checkbox-${props.node.id}`"
                    type="checkbox"
                    :value="props.node.id"
                    :checked="false"
                />
                <label :for="`checkbox-${props.node.id}`"></label>
            </div>
            <button
                :class="`button-name ${ props.depth === 0 ? 'button-name-parent' : '' }`"
                :disabled="isDisabled"
                :title="`Expand ${title}`"
                @click.prevent="onNameSelected"
                @keyup.enter="onNameSelected"
            >
                <span v-html="highlightTitleSearch"></span>
                <aside v-if="viewNodesStore.isSearchActive" v-html="highlightBreadcrumbSearch"></aside>
            </button>
            <button
                v-if="!store.isReadOnly"
                class="button-action-option icon-delete"
                :title="`Delete ${title}`"
                @click.prevent="onDeleteSelected"
                @keyup.enter="onDeleteSelected"
            >
                Delete {{ title }}
            </button>
            <button
                v-if="!store.isReadOnly"
                class="button-action-option icon-add"
                :title="`Add subsection to ${title}`"
            >
                Add subsection to {{ title }}
            </button>
            <button
                v-if="!store.isReadOnly"
                :class="`button-action-option icon-view`"
                title="Toggle visiblity"
            >
                Toggle visiblity
            </button>
            <button
                v-if="!store.isReadOnly"
                :class="`button-action-option ${ isUpdating ? 'icon-close' : 'icon-modify' }`"
                :title="`Update ${title}`"
                @click.prevent="onUpdateToggleSelected"
                @keyup.enter="onUpdateToggleSelected"
            >
                Update {{ title }}
            </button>
        </div>
        <mc-update-node v-if="isUpdating" />
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

.buttons-container {
    align-items: center;
    border-bottom: 1px solid var(--body-background);
    display: flex;
    margin: 0 0 0 v-bind(paddingLeft);
    padding: 2px;
}

.checklist {
    padding-top: 0.5rem;
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
    &:disabled {
        color: #222222;
        opacity: 1;
    }
}

    .button-name-parent {
        font-weight: bold;
    }

.button-action-option {
    @extend .button;
    color: var(--content-ui-foreground);
    font-size: 0rem;
    opacity: 0.7;
    padding: 0px 2px 0px 12px;
    &:hover {
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