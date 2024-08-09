<script setup lang="ts">
/**
 * Display the total mark for a students entire response
 */

import { getGradient } from "@js/util/cssUtils";
import { getPercentage } from "@js/util/mathUtils";
import { getLetterGrade, getLowerBound } from "@js/util/sbx/markUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";
import {
    MARK_TYPE_TOTAL_MARK,
    MARK_TYPE_PERCENTAGE,
    MARK_TYPE_LETTER_GRADE,
} from "@schoolboxAssessment/Types/MarkType";
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
 * Has a valid mark override that isn't a submission state
 * @returns {boolean} - whether the student has a mark override
 */
const hasMarkOverride = window.Vue.computed(() => {
    return props.student.attemptResponse.mark && !hasSubmissionState.value;
});

/**
 * Get the normaliseAs value for the student, which is the value to normalise the score to
 * @returns {number|null} - the normaliseAs value
 */
const normaliseAs = window.Vue.computed(() => {
    if (hasSubmissionState.value) {
        const submissionState = props.markData.submissionStates.find(
            (state) => state.displayAs === props.student.attemptResponse.mark,
        );
        return submissionState?.normaliseAs ?? null;
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
 * Only show the attempt mark when it actually exists
 * @returns {boolean} - whether the student has a mark
 */
const hasMark = window.Vue.computed(() => {
    return (
        props.student.attemptResponse.mark !== undefined &&
        props.student.attemptResponse.mark !== null &&
        props.student.attemptResponse.mark !== ""
    );
});

/**
 * Get the current mark, which also shows the relevant mark type
 * @returns {string} - the current mark
 */
const mark = window.Vue.computed(() => {
    if (hasSubmissionState.value) {
        return props.student.attemptResponse.mark;
    }
    // If the mark override is a percentage, we want to show the percentage
    if (hasMarkOverride.value && props.markData.type.slug === MARK_TYPE_PERCENTAGE) {
        return props.student.attemptResponse.mark?.includes("%")
            ? props.student.attemptResponse.mark
            : `${props.student.attemptResponse.mark}%`;
    }
    // If the mark override is a total mark or letter, we want to just show the mark
    if (
        (hasMarkOverride.value && props.markData.type.slug === MARK_TYPE_TOTAL_MARK) ||
        (hasMarkOverride.value && props.markData.type.slug === MARK_TYPE_LETTER_GRADE)
    ) {
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
 * Get the current mark style, which is a background colour that reflects the score
 * @returns {string} - the current mark style
 */
const statusStyles = window.Vue.computed(() => {
    // use the normaliseAs value if it exists to set the background colour
    if (hasSubmissionState.value && normaliseAs.value !== null) {
        return `${getGradient(normaliseAs.value, 100)}-bg`;
    }
    // ensure the background colour reflects the overridden percentage mark
    if (hasMarkOverride.value && props.markData.type.slug === MARK_TYPE_PERCENTAGE) {
        const markAsNum = Number(props.student.attemptResponse.mark?.replace("%", ""));
        return `${getGradient(markAsNum, 100)}-bg`;
    }
    // ensure the background colour reflects the overridden letter grade mark
    if (hasMarkOverride.value && props.markData.type.slug === MARK_TYPE_LETTER_GRADE) {
        const lowerBound = getLowerBound(
            props.student.attemptResponse.mark as string,
            props.markData.levels as LetterGradeItem[],
        ) as number;
        return `${getGradient(lowerBound, 100)}-bg`;
    }
    if (!props.student.isReviewed || (hasSubmissionState.value && normaliseAs.value === null)) {
        return `lrn-circle-${StudentItemMarkTypes.NOT_MARKED}`;
    }
    return `${getGradient(totalScore.value, maxScore.value)}-bg`;
});
</script>

<template>
    <!-- this is the matched DOM structure of a Learnosity score -->
    <div v-if="hasMark" class="lrn learnosity-report session-detail-by-item mark">
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
