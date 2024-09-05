<script setup lang="ts">
/**
 * A curriculum node to select to traverse further down the tree,
 * and add subsections or update the details on
 */

import type { Ref } from "vue";
import { gsap } from "gsap/all";
import { timeout } from "@js/util/jsUtils";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore } from "../store/manageCurriculumStore";
import { useViewNodesStore, DIRECTION } from "./store/mcViewNodesStore";
import { useActiveNodeStore } from "../activeNode/store/mcActiveNodeStore";
import { useAddCurriculumStore } from "../store/mcAddCurriculumStore";
import { useNodeUsageStore } from "../activeNode/store/mcNodeUsageStore";
import { useAddSubsectionStore } from "../activeNode/store/mcAddSubsectionStore";
import { useUpdateNodeStore } from "../activeNode/store/mcUpdateNodeStore";
import { useSearchStore } from "../store/mcSearchStore";
import McViewNodeCheckbox from "./mc-view-node-checkbox.vue";
import McViewNodeName from "./mc-view-node-name.vue";
import McViewNodeUsageSummary from "./mc-view-node-usage-summary.vue";
import McViewNodeActions from "./mc-view-node-actions.vue";
import McNodeUsageDetails from "../activeNode/mc-node-usage-details.vue";
import McAddSubsection from "../activeNode/mc-add-subsection.vue";
import McUpdateNode from "../activeNode/mc-update-node.vue";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();
const store = useStore();
const viewNodesStore = useViewNodesStore();
const activeNodeStore = useActiveNodeStore();
const addCurriculumStore = useAddCurriculumStore();
const nodeUsageStore = useNodeUsageStore();
const addSubsectionStore = useAddSubsectionStore();
const updateNodeStore = useUpdateNodeStore();
const searchStore = useSearchStore();
const viewNodeEl = window.Vue.ref(null); // to animate the node
const isNew = window.Vue.ref(false); // to highlight if the node has just been added
const justUpdated = window.Vue.ref(false); // to highlight if the node has just been updated

/**
 * Consider whether we are currently only showing enabled nodes
 * and hide the node if we are are and the node is not enabled
 * @returns { boolean }
 */
const showNode = window.Vue.computed(() => {
    if (!viewNodesStore.showDisabled && !props.node.enabled) {
        return false;
    }
    return true;
});

/**
 * animate to help show which direction we are currently moving through the tree
 * fade on the node and slide it from forwards/backwards depending on the currentDirection
 */
function animateOn(): void {
    const xStart = viewNodesStore.currentDirection === DIRECTION.FORWARDS ? -10 : 10;
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
 * show an animation when the node has been added or updated
 * we add the relevant boolean ref to show the animation then remove
 * @param change - the boolean value to change, can be either isNew or justUpdated
 */
async function displayAnimation(change: Ref<boolean>): Promise<void> {
    change.value = true;
    await timeout(3000);
    change.value = false;
}

/**
 * to animate on the node, checking also if this node is
 * a new subsection or entirely new curriculum and animating
 */

window.Vue.onMounted(async () => {
    if (showNode.value && props.depth === 0) {
        // we only need to animate the first node, as the rest are contained within
        animateOn();
    }
    if (props.node.id === addSubsectionStore.id) {
        // higlight the newly added node
        await displayAnimation(isNew);
        addSubsectionStore.unsetId();
    } else if (props.node.id === addCurriculumStore.id) {
        // highlight the new curriculum that's just been added
        await displayAnimation(isNew);
        addCurriculumStore.unsetId();
    }
});

/**
 * we listen for the actions we want to respond with
 * https://pinia.vuejs.org/core-concepts/actions.html#without-setup
 */
useUpdateNodeStore().$onAction(({ name, after }) => {
    if (name === "update") {
        after((result) => {
            if (props.node.id === result.id) {
                // highlight the just updated node
                void displayAnimation(justUpdated);
            }
        });
    }
});

/**
 * to gradually add more marginLeft to indicate node level depth
 * @returns { number }
 */
const marginLeft = window.Vue.computed(() => `calc(${props.depth * 2}rem)`);

/**
 * If this is the active node being viewed
 * @returns { boolean }
 */
const isViewing = window.Vue.computed(() => nodeUsageStore.isOpen && activeNodeStore.node.id === props.node.id);

/**
 * If there's another node being viewed
 * @returns { boolean }
 */
const isViewingAnother = window.Vue.computed(() => nodeUsageStore.isOpen && activeNodeStore.node.id !== props.node.id);

/**
 * If this is the active node being updated
 * @returns { boolean }
 */
const isUpdating = window.Vue.computed(() => updateNodeStore.isOpen && activeNodeStore.node.id === props.node.id);

/**
 * If there's another node being updated
 * @returns { boolean }
 */
const isUpdatingAnother = window.Vue.computed(
    () => updateNodeStore.isOpen && activeNodeStore.node.id !== props.node.id,
);

/**
 * If this is the active node being added to
 * @returns { boolean }
 */
const isAdding = window.Vue.computed(() => addSubsectionStore.isOpen && activeNodeStore.node.id === props.node.id);

/**
 * If there's another node being added to
 * @returns { boolean }
 */
const isAddingAnother = window.Vue.computed(
    () => addSubsectionStore.isOpen && activeNodeStore.node.id !== props.node.id,
);

/**
 * If this is the active node being viewed, updated or added to
 * @returns { boolean }
 */
const isActive = window.Vue.computed(() => isViewing.value || isUpdating.value || isAdding.value);

/**
 * To determine whether we need to display further child nodes within this node
 * @returns { boolean }
 */
const showChildren = window.Vue.computed(
    () => props.node.children && props.node.children.length > 0 && props.depth + 1 < 2,
);

/**
 * If there's a code available, which means it can be added to the name
 * @returns { boolean }
 */
const hasCode = window.Vue.computed(() => props.node.code && props.node.code !== "");

/**
 * Add in the code if it's available otherwise just show the title
 * @returns { string }
 */
const title = window.Vue.computed(() =>
    hasCode.value ? `[${props.node.code as string}] ${props.node.name}` : `${props.node.name}`,
);

/**
 * Whether the checkbox and actions should be displayed, shouldn't be if the user is read only
 * or we are at the root level node, or if the search is active
 * @returns { boolean }
 */
const showActions = window.Vue.computed(() => !store.isReadOnly && props.node.id !== UNSET_ID && !searchStore.isActive);

/**
 * Whether the usage should be displayed, shouldn't be if we are at the root level node,
 * or if the search is active
 * @returns { boolean }
 */
const showUsage = window.Vue.computed(() => props.node.id !== UNSET_ID);
</script>

<template>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <div v-if="showNode" ref="viewNodeEl" data-test-node>
            <div
                class="container"
                :data-enabled="props.node.enabled"
                :data-is-inactive="`${isViewingAnother || isUpdatingAnother || isAddingAnother ? true : false}`"
                :data-is-new="isNew"
                :data-just-updated="justUpdated"
            >
                <div class="inner-containers name-container">
                    <mc-view-node-checkbox v-if="showActions" :node="props.node" :is-active="isActive" />
                    <mc-view-node-name
                        :node="props.node"
                        :depth="props.depth"
                        :title="title"
                        :is-active="isActive"
                        data-test-view-node-name
                    />
                </div>
                <div class="inner-containers">
                    <mc-view-node-usage-summary
                        v-if="showUsage"
                        :node="props.node"
                        :title="title"
                        :is-viewing="isViewing"
                        :is-updating="isUpdating"
                        :is-adding="isAdding"
                    />
                    <mc-view-node-actions
                        v-if="showActions"
                        :node="props.node"
                        :title="title"
                        :is-viewing="isViewing"
                        :is-updating="isUpdating"
                        :is-adding="isAdding"
                    />
                </div>
            </div>
            <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                <mc-node-usage-details v-if="isViewing" :depth="props.depth" />
            </Transition>
            <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                <mc-add-subsection v-if="isAdding" :depth="props.depth" />
            </Transition>
            <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                <mc-update-node v-if="isUpdating" :depth="props.depth" />
            </Transition>
            <div v-if="showChildren">
                <mc-view-node
                    v-for="viewNode in props.node.children"
                    :key="viewNode.id"
                    :node="viewNode"
                    :depth="props.depth + 1"
                    data-test-view-node
                />
            </div>
        </div>
    </Transition>
</template>

<style lang="scss" scoped>
.container {
    align-items: center;
    border-bottom: 1px solid var(--body-background);
    display: flex;
    margin: 0 0 0 v-bind(marginLeft);
    min-height: 52px;
    overflow: hidden;
    padding: 2px;
    position: relative;
    transition: opacity 0.3s;
    @media (max-width: 500px) {
        // to allow more space for the name / buttons on mobile
        align-items: flex-start;
        flex-direction: column;
    }
}

.inner-containers {
    display: flex;
}

.name-container {
    width: 100%; // so the name can stretch to fill the space#
}

[data-is-inactive="true"] {
    opacity: 0.2;
    pointer-events: none;
}

[data-is-new="true"] {
    @extend .change-animation;
    animation-name: isNewAnimation;
}

[data-just-updated="true"] {
    @extend .change-animation;
    animation-name: justUpdatedAnimation;
}

.change-animation {
    animation-duration: 2s;
    animation-iteration-count: 1;
}

@keyframes isNewAnimation {
    0% {
        background-color: #4fa74f;
    } //is $success-color from _colors.scss
    100% {
        background-color: white;
    }
}

@keyframes justUpdatedAnimation {
    0% {
        background-color: var(--accent-background);
    }
    100% {
        background-color: white;
    }
}

@import "../style/mcStyle";
</style>
