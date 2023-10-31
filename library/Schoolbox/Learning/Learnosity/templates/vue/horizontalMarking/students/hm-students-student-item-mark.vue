<script setup lang="ts">
/**
 * Contains a students mark linked to an item, (only one item is shown at a time)
 */

import { gsap } from "gsap";
import { StudentItemMarkTypes } from "@schoolboxLearnosity/js/types/StudentItemMarkTypes";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    item: StudentItem; // the item to display
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
const markEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into

/**
 * To display the current item
 */
const display = window.Vue.computed(() => {
    return props.item.reference === itemsStore.activeItem.reference ? "block" : "none";
});

/**
 * We need to directly query the returned DOM element as the only reliable way to check if the item is marked
 */
// function checkIsMarked() {
//     const statusElement = markEl.value?.querySelector("[data-status]");
//     if (statusElement) {
//         const status = statusElement.getAttribute("data-status") as string;
//         const isMarked =
//             status === StudentItemMarkTypes.CORRECT ||
//             status === StudentItemMarkTypes.INCORRECT ||
//             status === StudentItemMarkTypes.PARTIAL;
//         studentsStore.updateItemIsMarked(props.item, isMarked);
//     }
// }

/**
 * Move the item mark element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
    const mark = props.container.querySelector(`[data-reference-title="${props.item.reference}"]`) as HTMLDivElement;
    if (mark) {
        (mark.firstChild as HTMLElement).nodeValue = ""; // clear the title, just leaving the mark design
        markEl.value?.appendChild(mark);
        gsap.from(markEl.value, { duration: 0.3, opacity: 0 });
        // checkIsMarked();
    }
});

/**
 * Get the current total score for the item
 * @returns {number} - the total score
 */
const totalScore = window.Vue.computed(() => {
    return props.item.questions.reduce((total, question) => {
        if (question.response.score) {
            return total + question.response.score;
        }
        return total;
    }, 0);
});

/**
 * Get the max score for the item
 * @returns {number} - the max score
 */
const maxScore = window.Vue.computed(() => {
    return props.item.questions.reduce((total, question) => {
        if (question.response.maxScore) {
            return total + question.response.maxScore;
        }
        return total;
    }, 0);
});

/**
 * Get the current status for the item
 * @returns {StudentItemMarkTypes} - the current status
 */
const status = window.Vue.computed(() => {
    if (!props.item.isMarked) {
        return StudentItemMarkTypes.NOT_MARKED;
    }
    if (totalScore.value === maxScore.value) {
        return StudentItemMarkTypes.CORRECT;
    }
    if (totalScore.value === 0) {
        return StudentItemMarkTypes.INCORRECT;
    }
    return StudentItemMarkTypes.PARTIAL;
});

/**
 * Get the current tooltip for the item
 * @returns {string} - the current tooltip
 */
const tooltip = window.Vue.computed(() => {
    if (status.value === StudentItemMarkTypes.NOT_MARKED) {
        return "Not marked";
    }
    if (status.value === StudentItemMarkTypes.CORRECT) {
        return "Correct";
    }
    if (status.value === StudentItemMarkTypes.INCORRECT) {
        return "Incorrect";
    }
    return "Partially correct";
});

/**
 * Update the span element with the current score
 * @param {HTMLDivElement} statusElement - the element to update
 */
function updateSpanElement(statusElement: HTMLDivElement) {
    const spanElement = statusElement?.querySelector("span") as HTMLSpanElement;
    spanElement.textContent = `${totalScore.value}/${maxScore.value}`;
}

/**
 * Update the hidden element which is used for accessibility
 * @param {HTMLDivElement} statusElement - the element to update
 */
function updateHiddenElement(statusElement: HTMLDivElement) {
    const hiddenElement = (statusElement?.parentElement as HTMLDivElement).querySelector(
        ".visually-hidden"
    ) as HTMLSpanElement;
    hiddenElement.textContent = ` ${tooltip.value} ${totalScore.value}/${maxScore.value}`;
}

/**
 * Update the styles when the score changes
 * @param {HTMLDivElement} statusElement - the element to update
 */
function updateStyles(statusElement: HTMLDivElement) {
    const classes = Array.from(statusElement.classList).filter((c) => c.includes("lrn-circle-")); // find any classes on statusElement that contain 'lrn-circle-*'
    classes.forEach((c) => statusElement.classList.remove(c)); // remove all classes that contain 'lrn-circle-*'
    statusElement.classList.add(`lrn-circle-${status.value}`); // add the correct class
}

/**
 * Update the score display anytime the score is changed
 */
function updateScoreDisplay() {
    const statusElement = markEl.value?.querySelector("[data-status]") as HTMLDivElement;
    statusElement.setAttribute("data-status", status.value);
    statusElement.setAttribute("data-original-title", tooltip.value);
    updateSpanElement(statusElement);
    updateHiddenElement(statusElement);
    updateStyles(statusElement);
    gsap.from(markEl.value, { duration: 0.3, opacity: 0 });
}

/**
 * Need to watch the item questions for any score updates, if any questions score changes we need to total up the new score and display
 * We also need to to check if the item is marked or not again based on if all questions that can be scored are now scored
 */
window.Vue.watch(
    () => props.item.questions,
    () => {
        updateScoreDisplay();
    },
    { deep: true }
);
</script>

<template>
    <!-- these classes are so the correct learnosity styles can be applied -->
    <div ref="markEl" class="lrn learnosity-report session-detail-by-item mark"></div>
</template>

<style scoped>
.mark {
    display: v-bind(display);
}
</style>
