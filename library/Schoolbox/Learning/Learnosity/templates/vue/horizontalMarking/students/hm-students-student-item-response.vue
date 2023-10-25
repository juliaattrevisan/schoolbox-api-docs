<script setup lang="ts">
/**
 * Contains a students response to an item
 */

import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";

interface Props {
    student: Student; // the student to display
    item: StudentItem; //
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const responseEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into

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
 * Add the response to the item to our element
 */
window.Vue.onMounted(() => {
    const response = props.container.querySelector(`[data-reference="${props.item.reference}"]`) as HTMLDivElement;
    responseEl.value?.appendChild(response);
    updateResponseStyles(response);
    addSampleAnswers(response);
});
</script>

<template>
    <div ref="responseEl"></div>
</template>
