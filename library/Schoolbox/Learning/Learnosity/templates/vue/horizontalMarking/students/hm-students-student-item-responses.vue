<script setup lang="ts">
/**
 * Contains a students response to an item
 */

import hmStudentsStudentItemResponsesScore from "./hm-students-student-item-responses-score.vue";
import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item to display
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const responsesEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into

/**
 * Update the styles of the response element to match the rest of the page
 * @param {HTMLDivElement} response - the response element to update the styles of
 */
function updateResponseStyles(response: HTMLDivElement) {
    const rowEl = response.querySelector(".row") as HTMLDivElement;
    rowEl.className = "";
    const colEl = response.querySelector(".col-xs-12") as HTMLDivElement;
    colEl.className = "l-flex-col l-gap-m";
}

/**
 * Add any sample answers to the questions in the items
 * @param {HTMLDivElement} response - the response element to add the sample answers to
 */
function addSampleAnswers(response: HTMLDivElement) {
    props.item.questions.forEach((question) => {
        if (question.metadata.sample_answer) {
            const addHtml = `
                <label>Sample Answer:</label> <em>${question.metadata.sample_answer}</em>
            `;
            const sampleAnswerEl = document.createElement("div");
            sampleAnswerEl.innerHTML = addHtml;
            const questionEl = response.querySelector(
                `[data-lrn-widget-container="${question.response_id}"]`
            ) as HTMLDivElement;
            questionEl.appendChild(sampleAnswerEl);
        }
    });
}

/**
 *
 * @param {HTMLDivElement} response - the response element to add the scores to
 */
function addScores(response: HTMLDivElement) {
    props.item.questions.forEach((question) => {
        const scoreElement = document.querySelector(
            `[data-learnosity-reference-score="${question.response_id}"]`
        ) as HTMLDivElement;
        const questionEl = response.querySelector(
            `[data-lrn-widget-container="${question.response_id}"]`
        ) as HTMLDivElement;
        questionEl.appendChild(scoreElement);
    });
}

/**
 * Mve the item response element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
    const response = props.container.querySelector(`[data-reference="${props.item.reference}"]`) as HTMLDivElement;
    responsesEl.value?.appendChild(response);
    if (response.dataset.hasMounted === undefined) {
        // we only need to run these methods once, otherwise we'll get duplicate sample answers etc
        updateResponseStyles(response);
        addSampleAnswers(response);
        addScores(response);
    }
    response.dataset.hasMounted = "true";
});

/**
 * Move the item response element back into the entire quiz response, so we keep it around for possible later use
 */
window.Vue.onBeforeUnmount(() => {
    const response = responsesEl.value?.querySelector(`[data-reference="${props.item.reference}"]`) as HTMLDivElement;
    props.container.appendChild(response);
});
</script>

<template>
    <div ref="responsesEl" class="item-responses"></div>
    <!--these scores are added in between each question response within responsesEl -->
    <hm-students-student-item-responses-score
        v-for="response in props.item.responses"
        :key="response.learnosityReference"
        :student="props.student"
        :item="props.item"
        :response="response"
    />
</template>

<style lang="scss">
.item-responses {
    border-top: 1px solid var(--body-background);
    border-bottom: 1px solid var(--body-background);
    padding: var(--spacing-m) 0;
    .lrn_widget {
        margin: 0;
        padding: 0;
    }
}
</style>
