<script setup lang="ts">
/**
 * Contains a students mark linked to an item, (only one item is shown at a time)
 */

import { gsap } from "gsap";
import type { Student } from "../types/Student";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import { StudentItemMarkTypes } from "../types/StudentItemMarkTypes";

interface Props {
    student: Student; // the item to display
    // container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
const markEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into

/**
 * To display the current item
 */
// const display = window.Vue.computed(() => {
//     return props.item.reference === itemsStore.activeItem.reference ? "block" : "none";
// });

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
// window.Vue.onMounted(() => {
//     const mark = props.container.querySelector(`[data-reference-title="${props.item.reference}"]`) as HTMLDivElement;
//     if (mark) {
//         (mark.firstChild as HTMLElement).nodeValue = ""; // clear the title, just leaving the mark design
//         markEl.value?.appendChild(mark);
//         checkIsMarked();
//     }
// });

/**
 * Get the current total score for the item
 */
// const totalScore = window.Vue.computed(() => {
//     return props.item.questions.reduce((total, question) => {
//         if (question.response.score) {
//             return total + question.response.score;
//         }
//         return total;
//     }, 0);
// });

/**
 * Get the max score for the item
 */
// const maxScore = window.Vue.computed(() => {
//     return props.item.questions.reduce((total, question) => {
//         if (question.response.maxScore) {
//             return total + question.response.maxScore;
//         }
//         return total;
//     }, 0);
// });

/**
 * Get the current status for the item
 */
// const status = window.Vue.computed(() => {
//     if (!props.item.isMarked) {
//         return StudentItemMarkTypes.NOT_MARKED;
//     }
//     if (totalScore.value === maxScore.value) {
//         return StudentItemMarkTypes.CORRECT;
//     }
//     if (totalScore.value === 0) {
//         return StudentItemMarkTypes.INCORRECT;
//     }
//     return StudentItemMarkTypes.PARTIAL;
// });

/**
 * Get the current tooltip for the item
 */
// const tooltip = window.Vue.computed(() => {
//     if (status.value === StudentItemMarkTypes.NOT_MARKED) {
//         return "Not marked";
//     }
//     if (status.value === StudentItemMarkTypes.CORRECT) {
//         return "Correct";
//     }
//     if (status.value === StudentItemMarkTypes.INCORRECT) {
//         return "Incorrect";
//     }
//     return "Partially correct";
// });

/**
 * Update the styles when the score changes
 * @param {HTMLDivElement} statusElement - the element to update
 */
// function updateStyles(statusElement: HTMLDivElement) {
//     // find any classes on statusElement that contain 'lrn-circle-*'
//     const classes = Array.from(statusElement.classList).filter((c) => c.includes("lrn-circle-"));
//     // remove all classes that contain 'lrn-circle-*'
//     classes.forEach((c) => statusElement.classList.remove(c));
//     // add the correct class
//     statusElement.classList.add(`lrn-circle-${status.value}`);
//     gsap.from(statusElement, { duration: 0.3, opacity: 0 });
// }

/**
 * Update the score display anytime the score is changed
 */
// function updateScoreDisplay() {
//     const statusElement = markEl.value?.querySelector("[data-status]") as HTMLDivElement;
//     const spanElement = statusElement?.querySelector("span") as HTMLSpanElement;
//     spanElement.textContent = `${totalScore.value}/${maxScore.value}`;
//     statusElement.setAttribute("data-status", status.value);
//     statusElement.setAttribute("data-original-title", tooltip.value);
//     updateStyles(statusElement);
// }
</script>

<template>
    <!-- these classes are so the correct learnosity styles can be applied -->
    <!-- <div ref="markEl" class="lrn learnosity-report session-detail-by-item mark"></div> -->
    <!-- <p>Hai</p> -->
    <div class="lrn learnosity-report session-detail-by-item mark">
        <h3 class="lrn-report-item-title" data-reference-title="c97b309a-a33d-473c-868e-72e5bbd7e2b5">
            <span class="visually-hidden"> Correct 3/3</span>
            <div
                aria-hidden="true"
                class="lrn-circle lrn-circle-correct pull-right"
                data-status="correct"
                data-toggle="tooltip"
                data-placement="left"
                data-original-title="Correct"
                tabindex="0"
            >
                <canvas width="24" height="24"></canvas>
                <span>3/3</span>
            </div>
        </h3>
    </div>
</template>

<style scoped>
/* .mark {
    display: v-bind(display);
} */
</style>
