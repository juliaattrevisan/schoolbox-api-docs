<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the actions
 */

import { gsap } from "gsap/all";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useModalViewNodesStore, DIRECTION } from "../store/csModalViewNodesStore";
import { useModalStore } from "../store/csModalStore";
import { useModalSearchStore } from "../store/csModalSearchStore";
import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import { useModalActiveNodeStore } from "../store/csModalActiveNodeStore";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import CsModalNodeRadio from "./cs-modal-node-radio.vue";
import CsModalNodeCheckbox from "./cs-modal-node-checkbox.vue";
import CsModalNodeName from "./cs-modal-node-name.vue";
import CsModalNodeUsageSummary from "./cs-modal-node-usage-summary.vue";
import CsModalNodeUsageDetails from "./cs-modal-node-usage-details.vue";

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

const modalStore = useModalStore();
const modalViewNodesStore = useModalViewNodesStore();
const modalActiveNodeStore = useModalActiveNodeStore();
const modalSearchStore = useModalSearchStore();
const modalNodeUsageStore = useModalNodeUsageStore();
const viewNodeEl = window.Vue.ref(null); //to animate the node

/**
 * to animate on the node
 */
window.Vue.onMounted(() => {
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
    const xStart = modalViewNodesStore.currentDirection === DIRECTION.FORWARDS ? -10 : 10;
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
const marginLeft = window.Vue.computed(() => `calc(${props.depth * 2}rem)`);

/**
 * To determine whether we need to display further child nodes within this node
 *
 * @returns { Boolean }
 */
const showChildren = window.Vue.computed(
    () => props.node.children && props.node.children.length > 0 && props.depth + 1 < 2,
);

/**
 * If there's a code available, which means it can be added to the name
 *
 * @returns { Boolean }
 */
const hasCode = window.Vue.computed(() => props.node.code && props.node.code !== "");

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = window.Vue.computed(() =>
    hasCode.value ? `[${props.node.code}] ${props.node.name}` : `${props.node.name}`,
);

/**
 * If there's another node being viewed
 *
 * @returns { boolean }
 */
const isViewingAnother = window.Vue.computed(
    () => modalNodeUsageStore.isOpen && modalActiveNodeStore.node.id !== props.node.id,
);

/**
 * Whether the usage should be displayed, shouldn't be if the modal is currently closed,
 * we are at the root level node or if the search is active
 *
 * @returns { boolean }
 */
const showUsage = window.Vue.computed(() => modalStore.isOpen && props.node.id !== UNSET_ID);

/**
 * If this is the active node being viewed
 *
 * @returns { boolean }
 */
const isViewing = window.Vue.computed(
    () => modalNodeUsageStore.isOpen && modalActiveNodeStore.node.id === props.node.id,
);
</script>

<template>
    <li ref="viewNodeEl">
        <div :class="`buttons-container`" :data-is-inactive="isViewingAnother">
            <cs-modal-node-radio v-if="modalStore.isSingleSelect" :node="props.node" />
            <cs-modal-node-checkbox v-else :node="props.node" />
            <cs-modal-node-name :node="props.node" :depth="props.depth" :title="title" :is-active="isViewing" />
            <cs-modal-node-usage-summary
                v-if="showUsage"
                :node="props.node"
                :title="title"
                :is-viewing="isViewing"
                :is-updating="false"
                :is-adding="false"
            />
        </div>
        <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <cs-modal-node-usage-details v-if="isViewing" :depth="props.depth" />
        </Transition>
        <ul v-if="showChildren">
            <cs-modal-node v-for="node in props.node.children" :key="node.id" :node="node" :depth="props.depth + 1" />
        </ul>
    </li>
</template>

<style lang="scss" scoped>
.buttons-container {
    align-items: center;
    border-bottom: 1px solid var(--body-background);
    display: flex;
    margin: 0 0 0 v-bind(marginLeft);
    min-height: 52px;
    overflow: hidden;
    padding: 2px;
    position: relative;
    transition: 0.3s;
}

[data-is-inactive="true"] {
    opacity: 0.2;
    pointer-events: none;
}

ul {
    list-style: none; //don't use any dots
    margin: 0; //and spacing is controlled by props.depth
}
</style>
