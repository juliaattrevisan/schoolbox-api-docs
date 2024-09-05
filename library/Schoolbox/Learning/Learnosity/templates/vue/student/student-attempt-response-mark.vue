<script setup lang="ts">
/**
 * To provide a final mark (grade) response for a student that can override the calculated mark.
 * Note the variation between mark and grade, which is a little confusing...
 * It's referred to the 'mark' in the backend, but the front end element is called 'grade', hence the variation.
 */

import { watchDebounced, computedEager } from "@vueuse/core";
import { timeout } from "@js/util/jsUtils";
import { getPercentage } from "@js/util/mathUtils";
import { getLetterGrade } from "@js/util/sbx/markUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";
import type { LetterGradeItem } from "@schoolboxAssessment/Types/LetterGradeItem";
import { MARK_TYPE_PERCENTAGE, MARK_TYPE_LETTER_GRADE } from "@schoolboxAssessment/Types/MarkType";
import { getAttemptScore, getAttemptMaxScore } from "@schoolboxLearnosity/js/util/markingUtils";

interface Props {
    student: Student; // the student to display
    isReadOnly: boolean; // whether the question is readonly, we don't need to init the ckeditor if it's readonly
    markData: AssessmentMarkData; // the mark type data linked to the assessment
}
const props = defineProps<Props>();

const emit = defineEmits<{
    updatedMark: [mark: string];
}>();

const gradeEl = window.Vue.ref<HTMLDivElement | null>(null);
const mark = window.Vue.ref(props.student.attemptResponse.mark ?? "");
const isSaving = window.Vue.ref(false);
const hasSaved = window.Vue.ref(false);
const hasError = window.Vue.ref(false);

/**
 * Here we retrieve the current mark by observing the relevant form field
 * As the input and validation are handled outside of Vue,
 * we need to observe the form field to update the mark
 * @param gradeContainer - the container to get the mark from
 */
function getMark(gradeContainer: HTMLElement): void {
    const formElement = gradeContainer.querySelector("input,select") as HTMLFormElement; // depending on the Mark Type, the mark field can either be an input or a select
    if (formElement !== null) {
        mark.value = formElement.value as string;
        emit("updatedMark", mark.value); // emit the initial value, as it's not currently returned in the initial payload
        // we need to observe the input value to update the mark when it changes
        const observer = new MutationObserver(() => {
            if (formElement.getAttribute("aria-invalid") === "true") {
                // ignore if the input is invalid
                return;
            }
            mark.value = formElement.value as string; // if it's valid, update the mark
        });
        observer.observe(formElement, { attributeFilter: ["value", "aria-invalid"] });
    }
}

/**
 * The grade field utilises a considerable pre-existing twig/JS structure that we can't easily replicate in Vue,
 * see for example: library/Schoolbox/Learning/MarkScheme/templates/TotalMarkFractional.twig.
 * Rather than trying to rewrite the whole thing for a Vue context, we just store the grade field outside of Vue,
 * and then append it into the Vue component when it's mounted.
 * See library/Schoolbox/Learning/Learnosity/templates/horizontal.twig and
 * library/Schoolbox/Learning/Learnosity/templates/feedback.twig for where the grade field is initially rendered.
 */
function addGradeElement(): void {
    const gradeContainer = document.getElementById(`grade-container-${props.student.id}`);
    if (gradeContainer !== null) {
        gradeEl.value?.append(gradeContainer);
        const form = gradeContainer.querySelector("form") as HTMLFormElement;
        form.style.marginBottom = "0"; // override the default margin so the save container is flush with the grade field
        getMark(gradeContainer);
    }
}

/**
 * To remove the grade field from the Vue component, to prevent it being destroyed when the component is unmounted
 * and to allow it to be re-appended when the component is potentially mounted again
 */
function removeGradeElement(): void {
    const gradesContainer = document.getElementById("grades-container");
    const gradeContainer = document.getElementById(`grade-container-${props.student.id}`);
    if (gradesContainer !== null && gradeContainer !== null) {
        gradesContainer.append(gradeContainer);
    }
}

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
const saveText = window.Vue.computed(() => {
    if (isSaving.value) {
        return "Saving...";
    }
    if (hasError.value) {
        return "Grade could not be saved";
    }
    return "Grade saved";
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
 * @param event - the event to prevent
 */
function beforeUnloadHandler(event: BeforeUnloadEvent): void {
    event.preventDefault();
    event.returnValue = "";
}

/**
 * Save the mark if it's changed
 */
async function saveMark(): Promise<void> {
    if (isSaving.value) {
        return;
    }
    if (mark.value === props.student.attemptResponse.mark) {
        return;
    }
    if (props.student.urls === undefined) {
        return;
    }
    try {
        isSaving.value = true;
        window.addEventListener("beforeunload", beforeUnloadHandler);
        const formData = new FormData();
        formData.append("mark", mark.value);
        const response = await fetch(props.student.urls.postFeedbackAttemptScore, {
            method: "POST",
            body: formData,
        });
        if (response.ok) {
            emit("updatedMark", mark.value);
            hasSaved.value = true;
        } else {
            hasError.value = true;
        }
        window.removeEventListener("beforeunload", beforeUnloadHandler);
        isSaving.value = false;
    } catch (error) {
        hasError.value = true;
    }
    await timeout(2000);
    hasSaved.value = false;
    hasError.value = false;
}

/**
 * save the mark when it's changed
 */
watchDebounced(
    mark,
    () => {
        void saveMark();
    },
    { debounce: 300 },
);

/**
 * Get the current total score for all items, so the total of all questions within each item
 * @returns {number} - the total score
 */
const currentScore = window.Vue.computed(() => {
    return getAttemptScore(props.student);
});

/**
 * Get the ACTUAL mark, which is the mark that's the accurate
 * representation of the calculated score, not something overridden by the user
 * @returns {string} - the current mark
 */
const actualMark = computedEager(() => {
    if (props.markData.type.slug === MARK_TYPE_PERCENTAGE) {
        const percentage = getPercentage(currentScore.value, props.student.attemptResponse.maxMarks as number);
        // percentage is returned from the back end with a space between the value and the %
        // so we need to set this up to match the format to compare the values for showAssignButton
        const percentageWithSpace =
            percentage !== undefined ? `${percentage.substring(0, percentage.length - 1)} %` : "";
        return percentageWithSpace;
    }
    if (props.markData.type.slug === MARK_TYPE_LETTER_GRADE) {
        return getLetterGrade(
            currentScore.value,
            props.student.attemptResponse.maxMarks as number,
            props.markData.levels as LetterGradeItem[],
        ) as string;
    }
    return `${currentScore.value} / ${props.student.attemptResponse.maxMarks}`;
});

/**
 * Only show the attempt mark when it actually exists
 * @returns {boolean} - whether the student has a mark
 */
const hasNoMark = window.Vue.computed(() => {
    return (
        props.student.attemptResponse.mark === undefined ||
        props.student.attemptResponse.mark === null ||
        props.student.attemptResponse.mark === ""
    );
});

/**
 * Whether to show the assign button, shown when the actual mark doesn't match the set overridden mark,
 * so users can easily re-assign the mark from the item scores
 * @returns {boolean} - whether to show the assign button
 */
const showAssignButton = computedEager(() => {
    if (hasNoMark.value || getAttemptMaxScore(props.student) === 0) {
        return false;
    }
    return actualMark.value !== mark.value;
});

/**
 * Update the mark which will fire the
 * watchDebounced and save the mark
 */
function assignMark(): void {
    const formElement = (gradeEl.value as HTMLDivElement).querySelector("input,select") as HTMLFormElement; // depending on the Mark Type, the mark field can either be an input or a select
    formElement.value = actualMark.value;
    mark.value = actualMark.value;
}

/**
 * Assign the mark when selected
 */
function onAssignSelected(): void {
    assignMark();
}

/**
 * If the user has updated the score, it's assumed that they wish
 * to override anything that they may have previously set in the grade field
 */
window.Vue.watch(currentScore, () => {
    assignMark();
    void saveMark();
});

/**
 * To add the grade field in
 */
window.Vue.onMounted(async () => {
    // don't need to proceed if it's readonly as the grade field won't be in the Vue component
    if (props.isReadOnly) {
        return;
    }
    await window.Vue.nextTick();
    addGradeElement();
    // check if the mark is empty, and if so assign the mark so when Finalise review is selected, the grade is visible on the quiz status page
    if (hasNoMark.value) {
        assignMark();
        void saveMark();
    }
});

/**
 * Return the grade field and save any unsaved changes
 */
window.Vue.onBeforeUnmount(async () => {
    // don't need to proceed if it's readonly as the grade field won't be in the Vue component
    if (props.isReadOnly) {
        return;
    }
    removeGradeElement();
    await saveMark();
});
</script>

<template>
    <div
        v-if="
            (props.isReadOnly && props.student.attemptResponse.mark === null) ||
            (props.isReadOnly && props.student.attemptResponse.mark === '')
        "
        class="l-flex-col l-gap-s"
    >
        <label>Grade</label>
        <p>
            <em>No grade added.</em>
        </p>
    </div>
    <div v-else-if="props.isReadOnly && props.student.attemptResponse.mark !== ''" class="l-flex-col l-gap-s">
        <label>Grade</label>
        <p>{{ props.student.attemptResponse.mark }}</p>
    </div>
    <div v-else class="grade-container">
        <div ref="gradeEl"></div>
        <div class="save-container l-grid">
            <div class="save-container__inner-container">
                <div :class="`alert-box f-no-margins ${hasSaved ? 'success' : ''} ${hasError ? 'alert' : ''}`">
                    <em>{{ saveText }}</em>
                </div>
            </div>
        </div>
        <button v-if="showAssignButton" class="full-width btn-assign" @click.prevent="onAssignSelected">
            Assign <b>{{ actualMark }}</b> from item scores
        </button>
    </div>
</template>

<style lang="scss" scoped>
.btn-assign {
    font-size: 0.8125rem;
}

.save-container {
    grid-template-rows: v-bind(saveContainerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}
</style>
