<script setup lang="ts">
/**
 * Contains a students response to an item and a score if available
 */

import { useFocus } from "@vueuse/core";
import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import type { StudentItemResponse } from "../types/StudentItemResponse";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item to display
    response: StudentItemResponse; // the response to display
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const responsesEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const scoreEl = window.Vue.ref<HTMLInputElement | null>(null);
const { focused } = useFocus(scoreEl);
const score = window.Vue.ref<number>(props.response.score ?? 0);
const isSaving = window.Vue.ref<boolean>(false);

/**
 * Save the score
 */
async function saveScore() {
    const formData = new FormData();
    formData.append("learnosityReference", props.response.learnosityReference);
    formData.append("score", score.value.toString());
    await fetch(props.student.urls.score, {
        method: "POST",
        body: formData,
    });
}

window.Vue.watch(focused, async ($focused) => {
    if (!$focused) {
        if (isSaving.value) {
            return;
        }
        await saveScore();
    }
});

/**
 * Save the score and comment, and then move to the next student
 */
// async function onSaveSelected() {
//     // studentsStore.updateStudentItemReview(props.item, score.value, comment.value);
//     // studentsStore.progressReport(props.student);
//     isSaving.value = true;
//     await saveScore();
//     isSaving.value = false;
// }

/**
 * Update the styles of the response element to match the rest of the page
 * @param {HTMLDivElement} response - the response element to update the styles of
 */
// function updateResponseStyles(response: HTMLDivElement) {
//     const rowEl = response.querySelector(".row") as HTMLDivElement;
//     rowEl.className = "";
//     const colEl = response.querySelector(".col-xs-12") as HTMLDivElement;
//     colEl.className = "l-flex-col l-gap-m";
// }

/**
 * Add any sample answers to the questions in the items
 * @param {HTMLDivElement} response - the response element to add the sample answers to
 */
// function addSampleAnswers(response: HTMLDivElement) {
//     props.item.questions.forEach((question) => {
//         if (question.metadata.sample_answer) {
//             const addHtml = `
//                 <label>Sample Answer:</label> <em>${question.metadata.sample_answer}</em>
//             `;
//             const sampleAnswerEl = document.createElement("div");
//             sampleAnswerEl.innerHTML = addHtml;
//             const questionEl = response.querySelector(
//                 `[data-lrn-widget-container="${question.response_id}"]`
//             ) as HTMLDivElement;
//             questionEl.appendChild(sampleAnswerEl);
//         }
//     });
// }

/**
 *
 * @param {HTMLDivElement} response - the response element to add the scores to
 */
// function addScores(response: HTMLDivElement) {
//     props.item.questions.forEach((question) => {
//         const scoreElement = document.querySelector(
//             `[data-learnosity-reference-score="${question.response_id}"]`
//         ) as HTMLDivElement;
//         const questionEl = response.querySelector(
//             `[data-lrn-widget-container="${question.response_id}"]`
//         ) as HTMLDivElement;
//         questionEl.appendChild(scoreElement);
//     });
// }

/**
 * Mve the item response element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
    const response = props.container.querySelector(
        `[data-lrn-widget-container="${props.response.learnosityReference}"]`
    ) as HTMLDivElement;
    responsesEl.value?.appendChild(response);
    if (response.dataset.hasMounted === undefined) {
        // we only need to run these methods once, otherwise we'll get duplicate sample answers etc
        // updateResponseStyles(response);
        // addSampleAnswers(response);
        // addScores(response);
    }
    response.dataset.hasMounted = "true";
});

/**
 * Move the item response element back into the entire quiz response, so we can add it back in if mounted again
 */
window.Vue.onBeforeUnmount(() => {
    const response = responsesEl.value?.querySelector(
        `[data-lrn-widget-container="${props.response.learnosityReference}"]`
    ) as HTMLDivElement;
    props.container.appendChild(response);
});
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <div ref="responsesEl"></div>
        <div :data-learnosity-reference-score="props.response.learnosityReference" class="response-score">
            <label>Update Score (max score: {{ props.response.maxScore }})</label>
            <input
                :id="`customise-score-${props.response.learnosityReference}`"
                ref="scoreEl"
                v-model="score"
                type="number"
                min="0"
                :max="props.response.maxScore"
            />
        </div>
    </div>
</template>
