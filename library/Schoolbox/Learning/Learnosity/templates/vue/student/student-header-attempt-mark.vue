<script setup lang="ts">
/**
 * Display the total mark for a students entire response
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import { StudentItemMarkTypes } from "@schoolboxLearnosity/js/types/StudentItemMarkTypes";
import { getAttemptScore, getAttemptMaxScore } from "@schoolboxLearnosity/js/util/markingUtils";

interface Props {
    student: Student; // the item to display
}
const props = defineProps<Props>();

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
 * Get the current status for the item
 * @returns {string} - the status
 */
const status = window.Vue.computed(() => {
    if (!props.student.isMarked) {
        return StudentItemMarkTypes.NOT_MARKED;
    }
    if (totalScore.value === maxScore.value) {
        return StudentItemMarkTypes.CORRECT;
    }
    if (totalScore.value === 0) {
        return StudentItemMarkTypes.INCORRECT;
    }
    return StudentItemMarkTypes.PARTIAL;
});

/**
 * Get the current tooltip for the item
 * @returns {string} - the tooltip
 */
const tooltip = window.Vue.computed(() => {
    if (status.value === StudentItemMarkTypes.NOT_MARKED) {
        return "Not fully marked";
    }
    if (status.value === StudentItemMarkTypes.CORRECT) {
        return "Correct";
    }
    if (status.value === StudentItemMarkTypes.INCORRECT) {
        return "Incorrect";
    }
    return "Partially correct";
});
</script>

<template>
    <!-- this is the matched DOM structure of a Learnosity score -->
    <div class="lrn learnosity-report session-detail-by-item mark">
        <h3 class="lrn-report-item-title">
            <span class="visually-hidden"> {{ tooltip }} {{ totalScore }}/{{ maxScore }}</span>
            <div
                aria-hidden="true"
                :class="`lrn-circle pull-right lrn-circle-${status}`"
                :data-status="status"
                data-toggle="tooltip"
                data-placement="left"
                :data-original-title="tooltip"
                tabindex="0"
            >
                <canvas width="24" height="24"></canvas>
                <span>{{ totalScore }}/{{ maxScore }}</span>
            </div>
        </h3>
    </div>
</template>
