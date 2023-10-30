<script setup lang="ts">
/**
 * To allow the teacher to add or update the score of an individual question
 */

import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import type { StudentItemResponse } from "../types/StudentItemResponse";
// import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
    item: StudentItem; //
    response: StudentItemResponse;
}
const props = defineProps<Props>();
// const studentsStore = useStudentsStore();
const scoreEl = window.Vue.ref<HTMLInputElement | null>(null);
const score = window.Vue.ref<number>(props.response.score ?? 0);
const isSaving = window.Vue.ref<boolean>(false);

/**
 * Save the score
 */
async function saveScore() {
    const formData = new FormData();
    // formData.append("learnosityReference", props.item.response.learnosityReference);
    // formData.append("score", score.value.toString());
    await fetch(props.student.urls.score, {
        method: "POST",
        body: formData,
    });
}

/**
 * Save the score and comment, and then move to the next student
 */
async function onSaveSelected() {
    // studentsStore.updateStudentItemReview(props.item, score.value, comment.value);
    // studentsStore.progressReport(props.student);
    isSaving.value = true;
    await saveScore();
    isSaving.value = false;
}

/**
 *
 */
window.Vue.onMounted(() => {
    console.warn("eh");
});

/**
 *
 */
window.Vue.onUnmounted(() => {
    // studentsStore.updateStudentItemReview(props.item, score.value, comment.value);
});
</script>

<template>
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
</template>

<style scoped>
.response-score {
    margin-top: 1rem;
}
/* need to overwrite the Learnosity styles */
.lrn label {
    font-size: 0.8125rem;
    font-weight: bold;
}
</style>
