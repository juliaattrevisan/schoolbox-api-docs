<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the edit button
 */

import { ref, computed, onMounted } from 'vue';
import { gsap } from 'gsap/all';
import { escapeRegExp } from '@js/util/jsUtils';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { useStore } from '../store/manageCurriculumStore';
import { useNodesStore, UNSET_ID } from '../store/mcNodesStore';
import { useViewNodesStore, DIRECTION } from './store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';
import { useDeleteNodeStore } from '../activeNode/store/mcDeleteNodeStore';
import { useAddSubsectionStore } from '../activeNode/store/mcAddSubsectionStore';
import { useEnableNodeStore } from '../activeNode/store/mcEnableNodeStore';
import { useUpdateNodeStore } from '../activeNode/store/mcUpdateNodeStore';
import McAddSubsection from '../activeNode/mc-add-subsection.vue';
import McUpdateNode from '../activeNode/mc-update-node.vue';


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
const nodesStore = useNodesStore();
const viewNodesStore = useViewNodesStore();
const activeNodeStore = useActiveNodeStore();
const deleteNodeStore = useDeleteNodeStore();
const addSubsectionStore = useAddSubsectionStore();
const enabledNodeStore = useEnableNodeStore();
const updateNodeStore = useUpdateNodeStore();

const viewNodeEl = ref(null);

/**
 *
 */
onMounted(() => {
    if (props.depth === 0) { //we only need to animate the first node, as the rest are contained within
        animateOn();
    }
});

/**
 * animate to help show which direction we are currently moving through the tree
 * fade on the node and slide it from forwards/backwards depending on the currentDirection
 */
function animateOn () {
    const xStart = viewNodesStore.currentDirection === DIRECTION.FORWARDS ? -5 : 5;
    gsap.set(viewNodeEl.value, {
        opacity: 0,
        x: xStart
    } );
    gsap.to(viewNodeEl.value, {
        ease: 'power1.out',
        duration: 0.3 ,
        opacity: 1,
        x: 0
    } );
}

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
    if (props.depth === 0) { //we can't go back any further
        return true;
    } else if (viewNodesStore.isSearchActive) { //cant do any navigation while search is active
        return true;
    } else {
        return false;
    }
});

/**
 * Only show the selected node and any of its direct children
 *
 * @returns { Boolean }
 */
const isIdSet = computed(() => props.node.id !== UNSET_ID);

/**
 * If this is the active node being updated
 *
 * @returns { Boolean }
 */
const isUpdating = computed(() => updateNodeStore.isOpen && activeNodeStore.node.id === props.node.id);

/**
 * If there's another node being updated
 *
 * @returns { Boolean }
 */
const isUpdatingAnother = computed(() => updateNodeStore.isOpen && activeNodeStore.node.id !== props.node.id);

/**
 * If this is the active node being updated
 *
 * @returns { Boolean }
 */
const isAdding = computed(() => addSubsectionStore.isOpen && activeNodeStore.node.id === props.node.id);

/**
 * If there's another node being updated
 *
 * @returns { Boolean }
 */
const isAddingAnother = computed(() => addSubsectionStore.isOpen && activeNodeStore.node.id !== props.node.id);

/**
 * traverse to the next nodel level
 */
function onNameSelected () {
    viewNodesStore.setNextSelected(props.node.id);
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
function onEnableToggleSelected () {
    props.node.enabled ? enabledNodeStore.prompt(props.node) : enabledNodeStore.enable(props.node);
}

/**
 * to open up or close the update modal
 */
function onAddSubsectionToggleSelected () {
    isAdding.value ? addSubsectionStore.cancel() : addSubsectionStore.prompt(props.node);
}

/**
 * to open up or close the update modal
 */
function onUpdateToggleSelected () {
    isUpdating.value ? updateNodeStore.cancel() : updateNodeStore.prompt(props.node);
}

/**
 * To determine whether we need to display further child nodes within this node
 *
 * @returns { Boolean }
 */
const showChildren = computed(() => props.node.children && props.node.children.length > 0 && (props.depth+1) < 2);

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
 * Slide down the node to be updated
 *
 * @param { DOMElement }
 */
async function onEnter (el, done) {
    await slideDown(el);
    done()
}

/**
 * Slide up the on close or when the node is updated
 *
 * @param { DOMElement }
 */
async function onLeave (el, done) {
    await slideUp(el);
    done()
}

</script>

<template>
    <li ref="viewNodeEl">
        <div :class="`buttons-container ${ isUpdatingAnother ? 'inactive' : '' } ${ isAddingAnother ? 'inactive' : '' }`">
            <div v-if="isIdSet" class="checklist">
                <input
                    :id="`checkbox-${props.node.id}`"
                    type="checkbox"
                    :value="props.node.id"
                    :checked="false"
                    :disabled="isUpdating"
                />
                <label :for="`checkbox-${props.node.id}`"></label>
            </div>
            <button
                :class="`button-name ${ isUpdating ? 'inactive' : '' } ${ isAdding ? 'inactive' : '' }`"
                :style="{ opacity: props.node.enabled ? '1' : '0.3' }"
                :title="`Expand ${title}`"
                :disabled="isDisabled"
                @click.prevent="onNameSelected"
                @keyup.enter="onNameSelected"
            >
                <span v-html="highlightTitleSearch"></span>
                <aside v-if="viewNodesStore.isSearchActive" v-html="highlightBreadcrumbSearch"></aside>
            </button>
            <button
                v-if="!store.isReadOnly"
                :class="`button-action-option icon-delete ${ isUpdating ? 'inactive' : '' } ${ isAdding ? 'inactive' : '' }`"
                :title="`Delete ${title}`"
                @click.prevent="onDeleteSelected"
                @keyup.enter="onDeleteSelected"
            >
                Delete {{ title }}
            </button>
            <button
                v-if="!store.isReadOnly"
                :class="`button-action-option ${ isUpdating ? 'inactive' : '' } ${ isAdding ? 'icon-close' : 'icon-add' }`"
                :title="`Add subsection to ${title}`"
                @click.prevent="onAddSubsectionToggleSelected"
                @keyup.enter="onAddSubsectionToggleSelected"
            >
                Add subsection to {{ title }}
            </button>
            <button
                v-if="!store.isReadOnly"
                :class="`button-action-option ${ props.node.enabled ? 'icon-view' : 'icon-view-hollow' } ${ isUpdating ? 'inactive' : '' } ${ isAdding ? 'inactive' : '' }`"
                :title="`${ props.node.enabled ? 'Disable' : 'Enable' } node`"
                @click.prevent="onEnableToggleSelected"
                @keyup.enter="onEnableToggleSelected"
            >
                Toggle visiblity
            </button>
            <button
                v-if="!store.isReadOnly"
                :class="`button-action-option ${ isUpdating ? 'icon-close' : 'icon-modify' } ${ isAdding ? 'inactive' : '' }`"
                :title="`Update ${title}`"
                @click.prevent="onUpdateToggleSelected"
                @keyup.enter="onUpdateToggleSelected"
            >
                Update {{ title }}
            </button>
        </div>
        <Transition
            @enter="onEnter"
            @leave="onLeave"
        >
            <mc-add-subsection v-if="isAdding" />
        </Transition>
        <Transition
            @enter="onEnter"
            @leave="onLeave"
        >
            <mc-update-node v-if="isUpdating" />
        </Transition>
        <ul v-if="showChildren ">
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
    transition: 0.3s;
}

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
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
    // &:enabledd { // @TODO - think about the various enabledd states more
    //     opacity: 0.2;
    // }
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
        font-weight: bold;
    }
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

.inactive {
    opacity: 0.2;
    pointer-events: none;
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