<script setup lang="ts">
/**
 * Contains a students response to an item and a score if available
 */

import { useFocus } from "@vueuse/core";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    question: StudentItemQuestion; // the question that's displayed
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const studentsStore = useStudentsStore();
const responsesEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const scoreEl = window.Vue.ref<HTMLInputElement | null>(null);
const { focused } = useFocus(scoreEl);
const score = window.Vue.ref<string>(props.question.response.score?.toString() ?? "");

/**
 * Save the score if it's changed
 */
async function saveScore() {
    if (score.value === "") {
        return;
    }
    if (score.value === props.question.response.score?.toString()) {
        return;
    }
    if (Number.isNaN(parseInt(score.value, 10))) {
        score.value = "";
        return;
    }
    studentsStore.updateItemResponseScore(props.student, props.item, props.question, parseInt(score.value, 10));
    const formData = new FormData();
    formData.append("learnosityReference", props.question.response.learnosityReference);
    formData.append("score", score.value);
    await fetch(props.student.urls.score, {
        method: "POST",
        body: formData,
    });
}

/**
 * add some quick validation to prevent the score input going below 0 and above the max score
 */
window.Vue.watch(score, (newScore) => {
    if (parseFloat(newScore) < 0) {
        score.value = "0";
    }
    if (props.question.response.maxScore && parseFloat(newScore) > props.question.response.maxScore) {
        score.value = props.question.response.maxScore.toString();
    }
});

/**
 * save the score on focus loss if the score has changed
 */
window.Vue.watch(focused, async ($focused) => {
    if (!$focused) {
        await saveScore();
    }
});

/**
 * Mve the item response element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
    const response = props.container.querySelector(
        `[data-lrn-widget-container="${props.question.response.learnosityReference}"]`
    ) as HTMLDivElement;
    responsesEl.value?.appendChild(response);
});

/**
 * Move the item response element back into the entire quiz response, so we can add it back in if mounted again
 * and also update the score in case the user has changed it
 */
window.Vue.onBeforeUnmount(async () => {
    const response = responsesEl.value?.querySelector(
        `[data-lrn-widget-container="${props.question.response.learnosityReference}"]`
    ) as HTMLDivElement;
    props.container.appendChild(response);
    await saveScore();
});
</script>

<template>
    <div class="l-flex-col l-gap-m l-pad-m--bottom question">
        <h3>Question</h3>
        <div ref="responsesEl"></div>
        <div v-if="props.question.metadata.sample_answer">
            <label>Sample answer</label>
            <input type="text" :value="question.metadata.sample_answer" readonly />
        </div>
        <div v-if="props.question.response.maxScore">
            <label>Update Score (max score: {{ props.question.response.maxScore }})</label>
            <input
                :id="`customise-score-${props.question.response.learnosityReference}`"
                ref="scoreEl"
                v-model="score"
                type="number"
                min="0"
                :max="props.question.response.maxScore"
            />
        </div>
    </div>
</template>

<style scoped>
.question {
    border-bottom: 4px solid var(--body-background);
}
/* as it's readonly, but we still want to make it fully readable  */
input[readonly] {
    background-color: var(--foreground-colour);
    color: #000;
}
</style>
