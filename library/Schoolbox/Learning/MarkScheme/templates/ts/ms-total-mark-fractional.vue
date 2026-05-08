<script setup lang="ts">
/**
 * This is where you type in a grade for the Response given by the student.
 * It applies to the "Total Mark" MarkType.
 *
 * For quizzes, each person's attempt can vary from other attempts, which occurs
 * when the content of quiz changes halfway through everyone doing it. The $limit
 * param, if specified, takes precedence over a derived value from the assessment's
 * MarkScheme instance.
 */

import MsTextInput from "./ms-text-input.vue";
import type { MarkSchemeTotalMarkFractional, SubmissionErrorStates } from "./markScheme";

// How many decimal places we allow
const PRECISION = 2;

interface Props {
    id: string | number;
    name?: string;
    markScheme: MarkSchemeTotalMarkFractional;
    submissionErrorStates: SubmissionErrorStates;
    tabIndex?: number;
    /**
     * Should this form input be rendered as readonly?
     */
    readonly?: boolean;
    class?: string;
    /**
     * Optional precedence: used for quiz & capability marks only
     * Maximum marks available per attempt, indexed by user id
     */
    limit?: number;
}

const props = defineProps<Props>();
const mark = defineModel<string | null>("mark", { required: true });

/**
 * Limit the decimal places to the defined precision if required
 * This logic mimics macro/limitDecimal.twig
 */
const limit = window.Vue.computed(() => {
    if (props.limit !== undefined) {
        return props.limit;
    }

    const { totalMark } = props.markScheme.context.conf;

    if (Math.floor(totalMark) !== totalMark) {
        return Number(totalMark.toFixed(PRECISION));
    }

    return Number(totalMark.toFixed(0));
});
</script>

<template>
    <ms-text-input
        :id="props.id"
        v-model:mark="mark"
        :name="props.name"
        :submission-error-states="props.submissionErrorStates"
        :class="props.class"
        :tab-index="props.tabIndex"
        :placeholder="props.readonly ? '(no mark given)' : `Mark out of ${limit}`"
        :limit="limit"
        :max-length="48"
        :input-title="`Maximum Mark: ${limit}`"
        :readonly="props.readonly"
        :alaress-validate-options="{
            withinRange: true,
            messages: {
                withinRange: `The grade must be between 0 and ${limit}.`,
            },
        }"
    />
</template>
