<script setup lang="ts">
/**
 * Contains a students response to a question and will save the score if updated
 */

import debounce from "lodash/debounce";
import { timeout } from "@js/util/jsUtils";
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
const score = window.Vue.ref<string>(props.question.response.score?.toString() ?? "");
const isSaving = window.Vue.ref(false);
const isSavedDelay = window.Vue.ref(false);

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
const saveText = window.Vue.computed(() => {
    if (isSaving.value) {
        return "Saving...";
    }
    return "Score saved";
});

/**
 * To open and close the save status
 * @returns {string} - the grid-template-rows
 */
const saveContainerHeight = window.Vue.computed(() => {
    if (isSaving.value || isSavedDelay.value) {
        return "1fr";
    }
    return "0fr";
});

/**
 * Save the score if it's changed and valid
 */
async function saveScore() {
    if (score.value.toString() === "") {
        return;
    }
    if (score.value.toString() === props.question.response.score?.toString()) {
        return;
    }
    if (Number.isNaN(parseInt(score.value, 10))) {
        score.value = "";
        return;
    }
    isSaving.value = true;
    const formData = new FormData();
    formData.append("learnosityReference", props.question.response.learnosityReference);
    formData.append("score", score.value);
    await fetch(props.student.urls.score, {
        method: "POST",
        body: formData,
    });
    studentsStore.updateItemResponseScore(props.student, props.item, props.question, parseInt(score.value, 10));
    isSavedDelay.value = true;
    isSaving.value = false;
    await timeout(2000);
    isSavedDelay.value = false;
}

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSave = debounce(() => {
    void saveScore();
}, 300);

/**
 * add some quick validation to prevent the score input going below 0 and above the max score and save the score
 */
window.Vue.watch(score, (newScore) => {
    if (parseFloat(newScore) < 0) {
        score.value = "0";
        return;
    }
    if (props.question.response.maxScore && parseFloat(newScore) > props.question.response.maxScore) {
        score.value = props.question.response.maxScore.toString();
        return;
    }
    debounceSave();
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
        <div ref="responsesEl"></div>
        <div v-if="props.question.metadata.sample_answer">
            <label>Sample answer</label>
            <input type="text" :value="question.metadata.sample_answer" readonly />
        </div>
        <div v-if="props.question.response.maxScore">
            <label>Score (max score: {{ props.question.response.maxScore }})</label>
            <input
                :id="`customise-score-${props.question.response.learnosityReference}`"
                ref="scoreEl"
                v-model="score"
                type="number"
                min="0"
                :max="props.question.response.maxScore"
            />
            <div class="save-container l-grid">
                <div class="save-container__inner-container">
                    <div :class="`alert-box f-no-margins ${isSavedDelay ? 'success' : ''}`">
                        <em>{{ saveText }}</em>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.save-container {
    grid-template-rows: v-bind(saveContainerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}
.question {
    border-bottom: 4px solid var(--body-background);
}
/* as it's readonly, but we still want to make it fully readable  */
input[readonly] {
    background-color: var(--foreground-colour);
    color: #000;
}
</style>
