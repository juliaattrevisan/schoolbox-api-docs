<script setup lang="ts">
/**
 * Contains a students response to a question and will save the score if updated
 */

import { watchDebounced } from "@vueuse/core";
import { timeout } from "@js/util/jsUtils";
import { removeClasses } from "@js/util/cssUtils";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
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
    showFeedback: boolean; // whether to show the feedback
    hasMarkVisibility: boolean; // whether marks and feedback are visible to the user
}
const props = defineProps<Props>();
const responsesEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const scoreEl = window.Vue.ref<HTMLInputElement | null>(null);
const score = window.Vue.ref<string>("");
const isSaving = window.Vue.ref(false);
const hasSaved = window.Vue.ref(false);
const hasError = window.Vue.ref(false);

const emit = defineEmits<{
    updatedScore: [question: StudentItemQuestion, score: number];
}>();

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
const saveText = window.Vue.computed(() => {
    if (isSaving.value) {
        return "Saving...";
    }
    if (hasError.value) {
        return "Sorry, the score could not be saved. Please refresh the page and try again. If the problem persists, contact your support.";
    }
    return "Score saved";
});

/**
 * To open and close the save status
 * @returns {string} - the grid-template-rows
 */
const saveContainerHeight = window.Vue.computed(() => {
    if (isSaving.value || hasSaved.value || hasError.value) {
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
    try {
        isSaving.value = true;
        window.addEventListener("beforeunload", beforeUnloadHandler);
        const formData = new FormData();
        formData.append(
            "learnosityReference",
            (props.question.response as StudentItemQuestionResponse).learnosityReference,
        );
        formData.append("score", score.value);
        const response = await fetch(props.student.urls.postFeedbackScore, {
            method: "POST",
            body: formData,
        });
        if (response.ok) {
            emit("updatedScore", props.question, parseFloat(score.value));
            hasSaved.value = true;
        } else {
            hasError.value = true;
        }
        window.removeEventListener("beforeunload", beforeUnloadHandler);
        isSaving.value = false;
    } catch (error) {
        isSaving.value = false;
        hasError.value = true;
    }
    await timeout(2000);
    if (hasSaved.value) {
        hasSaved.value = false;
    }
}

/**
 * add some validation to minimise the chance of invalid score input
 */
watchDebounced(
    score,
    (newScore) => {
        // Allow only digits and a single decimal point
        score.value = newScore.replace(/[^0-9.]/g, "");
        // Ensure only one decimal point is present
        const parts = newScore.split(".");
        if (parts.length > 2) {
            score.value = `${parts[0]}.${parts.slice(1).join("")}`;
        }
        // Prevent starting with a decimal point
        if (newScore.startsWith(".")) {
            score.value = `0${newScore}`;
        }
        // Prevent the score going below 0
        if (parseFloat(newScore) < 0) {
            score.value = "0";
            return;
        }
        // Keep it within the max score
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
    { debounce: 300 },
);

/**
 * Move the item response element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
    if (props.question.response) {
        const selector = `[data-lrn-widget-container="${props.question.response.learnosityReference}"]`;
        const response = props.container.querySelector(selector) as HTMLDivElement;
        if (response) {
            responsesEl.value?.appendChild(response);
        }
        // hide any incorrect/correct styling if we're not showing feedback
        if (response && !props.hasMarkVisibility) {
            removeClasses(response, ["lrn_incorrect", "lrn_correct"]);
        }
        score.value = props.question.response.score?.toString() ?? "";
        /**
         * Replace video placeholders with actual player when the ui has loaded,
         * ensuring we only do this on the first mount by checking if the replacement
         * container is already present
         */
        if (response && !response.querySelector("[data-schoolboxmedia-replacement-container]")) {
            // @ts-expect-error - schoolboxResource is available here, but untyped.
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
            $.fn.schoolboxResource_ProxyReplacement($(selector));
        }

        // Find any oembeds and load them
        responsesEl.value
            ?.querySelectorAll(
                "oembed[url]:not(:has(.iframely-embed)), div[data-oembed-url]:not(:has(.iframely-embed))",
            )
            .forEach((oEmbedEl) => {
                window.iframely.load(
                    oEmbedEl,
                    oEmbedEl.getAttribute("data-oembed-url") || oEmbedEl.getAttribute("url") || undefined,
                );
            });

        // Remove disabled attribute from the input field so field can be selected to view all the text within on smaller screens
        const inputElement = response?.querySelector(".lrn_textinput input");
        if (inputElement) {
            inputElement.removeAttribute("disabled");
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
            `[data-lrn-widget-container="${props.question.response.learnosityReference}"]`,
        ) as HTMLDivElement;
        props.container.appendChild(response);
        await saveScore();
    }
});
</script>

<template>
    <div class="l-flex-col l-gap-m question">
        <template v-if="props.question.response">
            <div ref="responsesEl"></div>
            <template v-if="props.showFeedback">
                <div v-if="props.question.metadata.sample_answer" class="f-no-margins">
                    <label>Sample answer</label>
                    <media-article
                        tag="div"
                        :content="props.question.metadata.sample_answer"
                        :sanitize="true"
                    ></media-article>
                </div>
                <div v-if="props.question.response?.maxScore" class="f-no-margins">
                    <!--
                    hasMarkVisibility determines what kind of label text you get
                    since it removes some superfluous words when they have no
                    contextual meaning - e.g. there's no point knowing "Score: "
                    when you weren't able to see it, due to visibility being set
                    to Staff Only. Also, when there's only one Question in the Item
                    just rely on the bubble in the top right corner which tells you
                    all this Score vs Max Score anyway
                    -->
                    <template v-if="props.isReadOnly">
                        <p v-if="props.item.questions.length > 1">
                            <template v-if="props.hasMarkVisibility">
                                <label>Score (max score: {{ props.question.response.maxScore }})</label>
                                {{ score }}
                            </template>
                            <template v-else>
                                <label>Max score: {{ props.question.response.maxScore }}</label>
                            </template>
                        </p>
                    </template>
                    <template v-else>
                        <label>Score (max score: {{ props.question.response.maxScore }})</label>
                        <input
                            :id="`customise-score-${props.question.response.learnosityReference}`"
                            ref="scoreEl"
                            v-model="score"
                            type="text"
                            inputmode="numeric"
                        />
                    </template>
                    <div class="save-container l-grid">
                        <div class="save-container__inner-container">
                            <div
                                :class="`alert-box f-no-margins ${hasSaved ? 'success' : ''} ${
                                    hasError ? 'alert' : ''
                                }`"
                            >
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

/* as it's readonly, but we still want to make it fully readable  */
input[readonly] {
    background-color: var(--color-foreground-primary);
    color: #000;
}
</style>
