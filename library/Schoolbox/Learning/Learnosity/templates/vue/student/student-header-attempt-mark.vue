<script setup lang="ts">
/**
 * Display the total mark for a students entire response
 */

import { getGradient } from "@js/util/cssUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { SubmissionState } from "@schoolboxAssessment/Types/SubmissionState";
import { StudentItemMarkTypes } from "@schoolboxLearnosity/js/types/StudentItemMarkTypes";
import { getAttemptScore, getAttemptMaxScore } from "@schoolboxLearnosity/js/util/markingUtils";

interface Props {
    student: Student; // the item to display
    submissionStates: SubmissionState[]; // the submission states that be used to override the automatically set mark
}
const props = defineProps<Props>();

/**
 * Whether the student has a submission state, which is a manual override of the mark
 * @returns {boolean} - whether the student has a submission state
 */
const hasSubmissionState = window.Vue.computed(() => {
    const submissionStateNames = props.submissionStates.map((state) => state.displayAs);
    return props.student.attemptResponse.mark && submissionStateNames.includes(props.student.attemptResponse.mark);
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
 * Get the current tooltip for the item
 * @returns {string} - the style to add a background colour that reflects the score
 */
const statusStyles = window.Vue.computed(() => {
    if (!props.student.isReviewed) {
        return `lrn-circle-${StudentItemMarkTypes.NOT_MARKED}`;
    }
    return `${getGradient(totalScore.value, maxScore.value)}-bg`;
});
</script>

<template>
    <div v-if="hasSubmissionState">
        <p>{{ props.student.attemptResponse.mark }}</p>
    </div>
    <!-- this is the matched DOM structure of a Learnosity score -->
    <div v-else-if="hasScore" class="lrn learnosity-report session-detail-by-item mark">
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
                <span>{{ totalScore }}/{{ maxScore }}</span>
            </div>
        </h3>
    </div>
</template>
