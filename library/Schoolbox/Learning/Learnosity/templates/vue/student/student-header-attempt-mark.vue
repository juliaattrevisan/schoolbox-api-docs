<script setup lang="ts">
/**
 * Display the total mark for a students entire response
 */

import { getGradient } from "@js/util/cssUtils";
import { getPercentage } from "@js/util/mathUtils";
import { getLetterGrade } from "@js/util/sbx/markUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";
import { MARK_TYPE_PERCENTAGE, MARK_TYPE_LETTER_GRADE } from "@schoolboxAssessment/Types/MarkType";
import type { LetterGradeItem } from "@schoolboxAssessment/Types/LetterGradeItem";
import { StudentItemMarkTypes } from "@schoolboxLearnosity/js/types/StudentItemMarkTypes";
import { getAttemptScore, getAttemptMaxScore } from "@schoolboxLearnosity/js/util/markingUtils";

interface Props {
    student: Student; // the item to display
    markData: AssessmentMarkData; // the mark type data linked to the assessment
}
const props = defineProps<Props>();

/**
 * Whether the student has a submission state, which is a manual override of the mark
 * @returns {boolean} - whether the student has a submission state
 */
const hasSubmissionState = window.Vue.computed(() => {
    const submissionStateNames = props.markData.submissionStates.map((state) => state.displayAs);
    return props.student.attemptResponse.mark && submissionStateNames.includes(props.student.attemptResponse.mark);
});

/**
 *
 * @returns {number|null} -
 */
const normaliseAs = window.Vue.computed(() => {
    if (hasSubmissionState.value) {
        const submissionState = props.markData.submissionStates.find(
            (state) => state.displayAs === props.student.attemptResponse.mark,
        );
        return submissionState?.normaliseAs ? submissionState.normaliseAs : null;
    }
    return null;
});

/**
 * Get the current total score for all items, so the total of all questions within each item
 * @returns {number} - the total score
 */
const totalScore = window.Vue.computed(() => {
    return getAttemptScore(props.student);
});

/**
 * Get the max score for all items
 * @returns {number} - the max score
 */
const maxScore = window.Vue.computed(() => {
    return getAttemptMaxScore(props.student);
});

/**
 * If the student has no score, we don't want to show any score
 * @returns {boolean} - whether the student has a score to display
 */
const hasScore = window.Vue.computed(() => {
    return maxScore.value !== 0;
});

/**
 * Get the current mark, which also shows the relevant mark type
 * @returns {string} - the current mark
 */
const mark = window.Vue.computed(() => {
    if (hasSubmissionState.value) {
        return props.student.attemptResponse.mark;
    }
    const score = `${totalScore.value}/${maxScore.value}`;
    if (props.markData.type.slug === MARK_TYPE_PERCENTAGE) {
        return `${getPercentage(totalScore.value, maxScore.value)} (${score})`;
    }
    if (props.markData.type.slug === MARK_TYPE_LETTER_GRADE) {
        return `${getLetterGrade(totalScore.value, maxScore.value, props.markData.levels as LetterGradeItem[])} (${score})`;
    }
    return score;
});

/**
 * Get the current tooltip for the item
 * @returns {string} - the style to add a background colour that reflects the score
 */
const statusStyles = window.Vue.computed(() => {
    if (hasSubmissionState.value && normaliseAs.value !== null) {
        return `${getGradient(normaliseAs.value, 100)}-bg`;
    }
    if (!props.student.isReviewed || (hasSubmissionState.value && normaliseAs.value === null)) {
        return `lrn-circle-${StudentItemMarkTypes.NOT_MARKED}`;
    }
    return `${getGradient(totalScore.value, maxScore.value)}-bg`;
});
</script>

<template>
    <!-- this is the matched DOM structure of a Learnosity score -->
    <div v-if="hasSubmissionState || hasScore" class="lrn learnosity-report session-detail-by-item mark">
        <h3 class="lrn-report-item-title">
            <span class="visually-hidden">{{ totalScore }}/{{ maxScore }}</span>
            <div
                aria-hidden="true"
                :class="`lrn-circle pull-right ${statusStyles}`"
                data-toggle="tooltip"
                data-placement="left"
                tabindex="0"
            >
                <canvas width="24" height="24"></canvas>
                <span>{{ mark }}</span>
            </div>
        </h3>
    </div>
</template>

<style scoped>
.lrn-circle {
    padding: 0.5rem 1rem; /* add some extra padding to the circle */
}
</style>
