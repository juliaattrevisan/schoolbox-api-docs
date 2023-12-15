<script setup lang="ts">
/**
 * Display the total mark for a students entire response
 */

import { getGradient } from "@js/util/cssUtils";
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
 * Get the current tooltip for the item
 * @returns {string} -
 */
const statusStyles = window.Vue.computed(() => {
    if (!props.student.isReviewed) {
        return `lrn-circle-${StudentItemMarkTypes.NOT_MARKED}`;
    }
    return `${getGradient(totalScore.value, maxScore.value)}-bg`;
});
</script>

<template>
    <!-- this is the matched DOM structure of a Learnosity score -->
    <div class="lrn learnosity-report session-detail-by-item mark">
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
