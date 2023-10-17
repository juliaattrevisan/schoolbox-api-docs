<script setup lang="ts">
/**
 * Display a single student's responses
 */

import { useCardsStore } from "./store/hmCardsStore";
import type { Student } from "./types/Student";

interface Props {
    student: Student; //
}
const props = defineProps<Props>();

const isOpen = window.Vue.ref(false); // whether the filters are shown
const studentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const containerEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const cardsStore = useCardsStore();
let learnosityContainer: HTMLDivElement;
let currentCardResponse: HTMLDivElement;

/**
 * To show/hide the filters when selected
 */
function onToggleSelected() {
    isOpen.value = !isOpen.value;
}

/**
 * To show/hide the filters container
 * @returns {string} - the height of the sub types container
 */
const containerHeight = window.Vue.computed(() => {
    return isOpen.value ? "1fr" : "0fr";
});

/**
 * Initialy hide all the children within the learnosity container
 */
function hideAllChildren() {
    Array.from(learnosityContainer.children).forEach((element) => {
        (element as HTMLDivElement).style.display = "none";
    });
}

/**
 * To display the current card
 */
function displayActiveCard() {
    currentCardResponse = learnosityContainer.querySelector(
        `[data-reference="${cardsStore.activeCard.reference}"]`
    ) as HTMLDivElement;
    currentCardResponse.style.display = "block";
}

/**
 * To change the current card
 */
function changeActiveCard() {
    currentCardResponse.style.display = "none";
    currentCardResponse = learnosityContainer.querySelector(
        `[data-reference="${cardsStore.activeCard.reference}"]`
    ) as HTMLDivElement;
    currentCardResponse.style.display = "block";
}

/**
 * When its loaded hide everything by default, and then only show the current card
 */
window.Vue.watch(
    () => props.student.isReportLoaded,
    async () => {
        if (props.student.isReportLoaded) {
            await window.Vue.nextTick(); // ensure the learnosity DOM is ready
            learnosityContainer = studentEl.value?.querySelector(`#${props.student.elementId}`) as HTMLDivElement;
            hideAllChildren();
            displayActiveCard();
        }
    }
);

/**
 * Show the appropriate response when the card is changed
 */
window.Vue.watch(
    () => cardsStore.activeCard,
    () => {
        changeActiveCard();
    }
);
</script>

<template>
    <div ref="studentEl" class="c-island l-flex-col l-pad-m">
        <div class="l-flex-row l-gap-m f-no-margins">
            <button
                v-if="props.student.isReportLoaded"
                :class="`toggle-button ${isOpen ? 'icon-up-arrow' : 'icon-down-arrow'}`"
                :disabled="!props.student.isReportLoaded"
                @click.prevent="onToggleSelected"
            ></button>
            <span v-else class="loader"></span>
            <h2>{{ props.student.name }}</h2>
        </div>
        <div class="response l-grid">
            <div ref="containerEl" class="response__inner-container">
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
