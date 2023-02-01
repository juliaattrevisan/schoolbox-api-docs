<script setup>
/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the actions
 */

import { ref, computed, onMounted } from "vue";
import { gsap } from "gsap/all";
import { useModalActiveNodesStore, DIRECTION } from "../store/csModalActiveNodesStore";
import { useModalSearchStore } from "../store/csModalSearchStore";
import CsModalNodeCheckbox from "./cs-modal-node-checkbox.vue";
import CsModalNodeName from "./cs-modal-node-name.vue";

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
});

const modalActiveNodesStore = useModalActiveNodesStore();
const modalSearchStore = useModalSearchStore();
const viewNodeEl = ref(null); //to animate the node

/**
 * to animate on the node
 */
onMounted(() => {
    if (props.depth === 0) {
        //we only need to animate the first node, as the rest are contained within
        animateOn();
    }
});

/**
 * animate to help show which direction we are currently moving through the tree
 * fade on the node and slide it from forwards/backwards depending on the currentDirection
 */
function animateOn() {
    const xStart = modalActiveNodesStore.currentDirection === DIRECTION.FORWARDS ? -10 : 10;
    gsap.set(viewNodeEl.value, {
        opacity: 0,
        x: xStart,
    });
    gsap.to(viewNodeEl.value, {
        ease: "power1.out",
        duration: 0.3,
        opacity: 1,
        x: 0,
    });
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
    if (modalSearchStore.isActive || props.depth !== 0) {
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
const showChildren = computed(() => props.node.children && props.node.children.length > 0 && props.depth + 1 < 2);

/**
 * If there's a code available, which means it can be added to the name
 *
 * @returns { Boolean }
 */
const hasCode = computed(() => props.node.code && props.node.code !== "");

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => (hasCode.value ? `[${props.node.code}] ${props.node.name}` : `${props.node.name}`));
</script>

<template>
    <li ref="viewNodeEl">
        <div :class="`buttons-container`">
            <cs-modal-node-checkbox :node="props.node" />
            <cs-modal-node-name :node="props.node" :depth="props.depth" :title="title" />
        </div>
        <ul v-if="showChildren">
            <cs-modal-node v-for="node in props.node.children" :key="node.id" :node="node" :depth="props.depth + 1" />
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

ul {
    list-style: none; //don't use any dots
    margin: 0; //and spacing is controlled by props.depth
}
</style>
