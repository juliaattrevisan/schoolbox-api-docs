<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the actions
 */

import { ref, computed, onMounted } from 'vue';
import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { useStore } from '../store/manageCurriculumStore';
import { useNodesStore, UNSET_ID } from '../store/mcNodesStore';
import { useViewNodesStore, DIRECTION } from './store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';
import { useAddSubsectionStore } from '../activeNode/store/mcAddSubsectionStore';
import { useUpdateNodeStore } from '../activeNode/store/mcUpdateNodeStore';
// import McViewNodeCheckbox from './mc-view-node-checkbox.vue';
import McViewNodeName from './mc-view-node-name.vue';
import McViewNodeActions from './mc-view-node-actions.vue';
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
     * to gradually add more marginLeft to indicate node level depth
     */
    depth: {
        type: Number,
        required: true,
    },
});

const store = useStore();
const viewNodesStore = useViewNodesStore();
const activeNodeStore = useActiveNodeStore();
const addSubsectionStore = useAddSubsectionStore();
const updateNodeStore = useUpdateNodeStore();
const viewNodeEl = ref(null); //to animate the node

/**
 * to animate on the node
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
    const xStart = viewNodesStore.currentDirection === DIRECTION.FORWARDS ? -10 : 10;
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
 * to gradually add more marginLeft to indicate node level depth
 *
 * @returns { Number }
 */
const marginLeft = computed(() => `calc(${props.depth * 2}rem)`);


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
 * If this is the active node being added to
 *
 * @returns { Boolean }
 */
const isAdding = computed(() => addSubsectionStore.isOpen && activeNodeStore.node.id === props.node.id);

/**
 * If there's another node being added to
 *
 * @returns { Boolean }
 */
const isAddingAnother = computed(() => addSubsectionStore.isOpen && activeNodeStore.node.id !== props.node.id);

/**
 * If this is the active node being updated or added to
 *
 * @returns { Boolean }
 */
const isUpdatingOrAdding = computed(() => isUpdating.value || isAdding.value);

/**
 * To determine whether we need to display further child nodes within this node
 *
 * @returns { Boolean }
 */
const showChildren = computed(() => props.node.children && props.node.children.length > 0 && (props.depth+1) < 2);

/**
 * If there's a code available, which means it can be added to the name
 *
 * @returns { Boolean }
 */
 const hasCode = computed(() => props.node.code && props.node.code !== '');

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => hasCode.value ? `[${props.node.code}] ${props.node.name}` : `${props.node.name}`);

/**
 * Whether the buttons should be displayed, shouldn't be if the user is read only
 * or are are at the root level node
 *
 * @returns { Boolean }
 */
const showActions = computed(() => !store.isReadOnly && props.node.id !== UNSET_ID);

/**
 * Slide down the node to be updated / added
 *
 * @param { DOMElement }
 */
async function onEnter (el, done) {
    await slideDown(el);
    done()
}

/**
 * Slide up on close or when the node is updated  / added
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
        <div :class="`buttons-container ${ isUpdatingAnother || isAddingAnother ? 'inactive' : '' } ${ props.node.enabled ? '' : 'not-enabled' }`">
            <!-- <mc-view-node-checkbox
                :node="props.node"
                :isUpdatingOrAdding="isUpdatingOrAdding"
            /> -->
            <mc-view-node-name
                :node="props.node"
                :depth="props.depth"
                :title="title"
                :isUpdatingOrAdding="isUpdatingOrAdding"
            />
            <mc-view-node-actions
                v-if="showActions"
                :node="props.node"
                :title="title"
                :isUpdating="isUpdating"
                :isAdding="isAdding"
                :isUpdatingOrAdding="isUpdatingOrAdding"
            />
            <!-- <div class="alert-box success messsage-container">
                Node successfully updated
                <a href="" class="close">×</a>
            </div> -->
        </div>
        <Transition @enter="onEnter" @leave="onLeave">
            <mc-add-subsection v-if="isAdding" :depth="props.depth" />
        </Transition>
        <Transition @enter="onEnter" @leave="onLeave" >
            <mc-update-node v-if="isUpdating" :depth="props.depth" />
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
    margin: 0 0 0 v-bind(marginLeft);
    padding: 2px;
    position: relative;
    transition: 0.3s;
}

.messsage-container {
    height: 100%;
    left: 0;
    top: 0;
    position: absolute;
    width: 100%;
}

ul {
    list-style: none; //don't use any dots
    margin: 0; //and spacing is controlled by props.depth
}

.inactive {
    opacity: 0.2;
    pointer-events: none;
}

.not-enabled {
    background: #F6F6F6;
}

</style>