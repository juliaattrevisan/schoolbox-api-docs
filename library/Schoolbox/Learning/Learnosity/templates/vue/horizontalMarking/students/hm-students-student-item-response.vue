<script setup lang="ts">
/**
 * Contains a students response to an item and a score if available
 */

import { useFocus } from "@vueuse/core";
import { useStudentsStore } from "../store/hmStudentsStore";
import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import type { Question } from "../types/Question";
import type { StudentItemResponse } from "../types/StudentItemResponse";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item to display
    question: Question; // the question that's displayed
    response: StudentItemResponse; // the response to display
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const studentsStore = useStudentsStore();
const responsesEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const scoreEl = window.Vue.ref<HTMLInputElement | null>(null);
const { focused } = useFocus(scoreEl);
const score = window.Vue.ref<number>(props.response.score ?? 0);

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

/**
 * save the score on focus loss
 */
window.Vue.watch(focused, async ($focused) => {
    if (!$focused) {
        studentsStore.updateResponseScore(props.response, score.value);
        await saveScore();
    }
});

/**
 * Mve the item response element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
    const response = props.container.querySelector(
        `[data-lrn-widget-container="${props.response.learnosityReference}"]`
    ) as HTMLDivElement;
    responsesEl.value?.appendChild(response);
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
        <div v-if="question.metadata.sample_answer">
            <label>Sample answer</label>
            <input type="text" :value="question.metadata.sample_answer" disabled />
        </div>
        <div>
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
