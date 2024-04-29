<script setup lang="ts">
/**
 * The Unit planner header, containing the title and functionality to add a new unit
 */

import { slideDown, slideUp } from "@js/util/animateUtils";
import { useAddUnitStore } from "./store/unitPlannerAddUnitStore";
import UpAddUnit from "./up-add-unit.vue";

interface Props {
    courseTitle: string;
}
const props = defineProps<Props>();
const addUnitStore = useAddUnitStore();

/**
 * To show / hide the add unit display
 */
function onCreateNewSelected() {
    if (!addUnitStore.isOpen) {
        addUnitStore.prompt();
    }
}

/**
 * Slide down to show the add curriculum display
 * @param {Element} element - The element to animate
 * @param {Function} done - Callback to call when the animation is complete
 */
function onEnter(element: Element, done: () => void) {
    void slideDown(element as HTMLElement).then(() => done());
}

/**
 * Slide up on close or when the curriculum is added
 * @param {Element} element - The element to animate
 * @param {Function} done - Callback to call when the animation is complete
 */
function onLeave(element: Element, done: () => void) {
    void slideUp(element as HTMLElement).then(() => done());
}
</script>

<template>
    <div class="row actions-small-1 actions-medium-2">
        <div class="button-action small-12 columns">
            <h1>Unit Planner - {{ props.courseTitle }}</h1>
            <button
                class="button icon-add"
                data-interaction="add-unit"
                data-test-add-unit
                @click.prevent="onCreateNewSelected"
            >
                Add Unit
            </button>
        </div>
    </div>
    <Transition @enter="onEnter" @leave="onLeave">
        <up-add-unit v-if="addUnitStore.isOpen" />
    </Transition>
</template>

<style scoped></style>
