<script setup lang="ts">
/**
 * Contains a students response to a question and will save the score if updated
 */

import { watchDebounced } from "@vueuse/core";
import { timeout } from "@js/util/jsUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    question: StudentItemQuestion; // the question that's displayed
    container: HTMLElement; // the parent container, to look for the relevant item
    isReadOnly: boolean; // whether the question is readonly
    isReviewed: boolean; // whether the question has been reviewed
}
const props = defineProps<Props>();
const responsesEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const scoreEl = window.Vue.ref<HTMLInputElement | null>(null);
const score = window.Vue.ref<string>("");
const isSaving = window.Vue.ref(false);
const isSavedDelay = window.Vue.ref(false);

const emit = defineEmits<{
    updatedScore: [question: StudentItemQuestion, score: number];
}>();

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
const saveText = window.Vue.computed(() => {
    return isSaving.value ? "Saving..." : "Score saved";
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
 * To notify the user that they're leaving the page while data is being saved
 * @param {BeforeUnloadEvent} event - the event to prevent
 */
function beforeUnloadHandler(event: BeforeUnloadEvent) {
    event.preventDefault();
    event.returnValue = "";
}

/**
 * Save the score if it's changed and valid
 */
async function saveScore() {
    if (isSaving.value) {
        return;
    }
    if (props.student.urls === undefined) {
        return;
    }
    if (score.value.toString() === "") {
        return;
    }
    if (score.value.toString() === props.question.response?.score?.toString()) {
        return;
    }
    if (Number.isNaN(parseFloat(score.value))) {
        score.value = "";
        return;
    }
    isSaving.value = true;
    emit("updatedScore", props.question, parseFloat(score.value));
    window.addEventListener("beforeunload", beforeUnloadHandler);
    const formData = new FormData();
    formData.append(
        "learnosityReference",
        (props.question.response as StudentItemQuestionResponse).learnosityReference
    );
    formData.append("score", score.value);
    await fetch(props.student.urls.score, {
        method: "POST",
        body: formData,
    });
    window.removeEventListener("beforeunload", beforeUnloadHandler);
    isSavedDelay.value = true;
    isSaving.value = false;
    await timeout(2000);
    isSavedDelay.value = false;
}

/**
 * add some quick validation to prevent the score input going below 0 and above the max score and save the score
 */
watchDebounced(
    score,
    (newScore) => {
        if (parseFloat(newScore) < 0) {
            score.value = "0";
            return;
        }
        // keep it within the max score
        if (
            props.question.response &&
            props.question.response?.maxScore &&
            parseFloat(newScore) > props.question.response.maxScore
        ) {
            score.value = props.question.response.maxScore.toString();
            return;
        }
        void saveScore();
    },
    { debounce: 300 }
);

/**
 * Move the item response element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
  if (props.question.response) {
      const selector = `[data-lrn-widget-container="${props.question.response.learnosityReference}"]`;
      const response = props.container.querySelector(selector) as HTMLDivElement;

      responsesEl.value?.appendChild(response);
      score.value = props.question.response.score?.toString() ?? "";

      /**
       * Replace video placeholders with actual player when the ui has loaded,
       * ensuring we only do this on the first mount by checking if the replacement
       * container is already present
       */
      if (!response.querySelector("[data-schoolboxmedia-replacement-container]")) {
          $.fn.schoolboxResource_ProxyReplacement($(selector));
      }
  }
});

/**
 * Move the item response element back into the entire quiz response, so we can add it back in if mounted again
 * and also update the score in case the user has changed it
 */
window.Vue.onBeforeUnmount(async () => {
    if (props.question.response) {
        const response = responsesEl.value?.querySelector(
            `[data-lrn-widget-container="${props.question.response.learnosityReference}"]`
        ) as HTMLDivElement;
        props.container.appendChild(response);
        await saveScore();
    }
});
</script>

<template>
    <div class="l-flex-col l-gap-m l-pad-m--bottom question">
        <template v-if="props.question.response">
            <div ref="responsesEl"></div>
            <template v-if="props.isReviewed">
                <div v-if="props.question.metadata.sample_answer" class="f-no-margins">
                    <label>Sample answer</label>
                    <p>{{ question.metadata.sample_answer }}</p>
                </div>
                <div v-if="props.question.response?.maxScore" class="f-no-margins">
                    <label>Score (max score: {{ props.question.response.maxScore }})</label>
                    <p v-if="props.isReadOnly">{{ score }}</p>
                    <input
                        v-else
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
            </template>
        </template>
        <div v-else class="alert-box f-no-margins">
            This question was not in the quiz when the student attempted the quiz.
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
    border-bottom: 2px solid var(--body-background);
}
/* as it's readonly, but we still want to make it fully readable  */
input[readonly] {
    background-color: var(--foreground-colour);
    color: #000;
}
</style>
