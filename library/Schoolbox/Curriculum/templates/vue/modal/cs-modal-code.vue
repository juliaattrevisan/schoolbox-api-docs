<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the actions
 */

import { ref, computed, onMounted } from 'vue';
import { gsap } from 'gsap/all';
import { useModalActiveCodesStore, DIRECTION } from '../store/csModalActiveCodesStore';
// import { useSearchStore } from '../store/mcSearchStore';
// import McViewNodeCheckbox from './mc-view-node-checkbox.vue';
import CsModalCodeName from './cs-modal-code-name.vue';


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

const modalActiveCodesStore = useModalActiveCodesStore();
// const searchStore = useSearchStore();
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
    const xStart = modalActiveCodesStore.currentDirection === DIRECTION.FORWARDS ? -10 : 10;
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
 * To better highlight the current top level node from its children
 *
 * @returns { String }
 */
const borderBottomColour = computed(() => {
    // if (searchStore.isActive || props.depth !== 0) {
    if (props.depth !== 0) {
        return `var(--body-background)`;
    } else if (props.depth === 0) {
        return `var(--body-foreground-h1)`;
    }
});

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

</script>

<template>
    <li ref="viewNodeEl">
        <div :class="`buttons-container`">
            <!-- <cs-modal-code-checkbox
                :node="props.node"
            /> -->
            <cs-modal-code-name
                :node="props.node"
                :depth="props.depth"
                :title="title"
            />
        </div>
        <ul v-if="showChildren">
            <cs-modal-code
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
    border-bottom: 1px solid v-bind(borderBottomColour);
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

// @import '../style/mcStyle';

</style>
