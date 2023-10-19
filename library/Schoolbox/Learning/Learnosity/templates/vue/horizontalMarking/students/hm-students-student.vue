<script setup lang="ts">
/**
 * Display a single student's responses
 */

import { hideAllChildren } from "@js/util/domUtils";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import type { Student } from "../types/Student";

interface Props {
    student: Student; //
}
const props = defineProps<Props>();

const studentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
let learnosityContainer: HTMLDivElement;
let currentCardResponse: HTMLDivElement;

/**
 * To show/hide the response when selected
 */
function onToggleSelected() {
    studentsStore.toggleStudent(props.student);
}

/**
 * To show/hide the response container
 * @returns {string} - the height of the response container
 */
const containerHeight = window.Vue.computed(() => {
    return props.student.isExpanded ? "1fr" : "0fr";
});

/**
 * To display the current item
 */
function displayActiveItem() {
    currentCardResponse = learnosityContainer.querySelector(
        `[data-reference="${itemsStore.activeItem.reference}"]`
    ) as HTMLDivElement;
    currentCardResponse.style.display = "block";
}

/**
 * To change the current item
 */
function changeActiveItem() {
    currentCardResponse.style.display = "none";
    currentCardResponse = learnosityContainer.querySelector(
        `[data-reference="${itemsStore.activeItem.reference}"]`
    ) as HTMLDivElement;
    currentCardResponse.style.display = "block";
}

/**
 * When its loaded hide everything by default, and then only show the current item
 */
window.Vue.watch(
    () => props.student.isReportLoaded,
    async () => {
        if (props.student.isReportLoaded) {
            await window.Vue.nextTick(); // ensure the learnosity DOM is ready
            learnosityContainer = studentEl.value?.querySelector(`#${props.student.elementId}`) as HTMLDivElement;
            hideAllChildren(learnosityContainer);
            displayActiveItem();
        }
    }
);

/**
 * Show the appropriate response when the item is changed
 */
window.Vue.watch(
    () => itemsStore.activeItem,
    () => {
        changeActiveItem();
    }
);
</script>

<template>
    <div ref="studentEl" class="c-island l-flex-col l-pad-m">
        <div class="l-flex-row l-gap-m f-no-margins">
            <button
                v-if="props.student.isReportLoaded"
                :class="`toggle-button ${props.student.isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'}`"
                :disabled="!props.student.isReportLoaded"
                @click.prevent="onToggleSelected"
            ></button>
            <span v-else class="loader"></span>
            <h2>{{ props.student.name }}</h2>
        </div>
        <div class="response l-grid">
            <div class="response__inner-container">
                <div :id="props.student.elementId"></div>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.toggle-button {
    background: none;
    color: var(--content-ui-foreground);
}
.response {
    background-color: var(--foreground-colour);
    grid-template-rows: v-bind(containerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}
</style>
