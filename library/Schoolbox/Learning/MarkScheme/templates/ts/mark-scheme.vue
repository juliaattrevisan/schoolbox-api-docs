<script setup lang="ts">
/**
 * Renders an input aligning with a given mark scheme
 * This is equivalent to the twig templates in library/Schoolbox/Learning/MarkScheme/templates
 *
 * Currently integrates with jquery.combobox.js and jquery.alaressValidate.js down the chain
 */

import type { MarkScheme, SubmissionErrorStates } from "./markScheme";
import MsLetterGrade from "./ms-letter-grade.vue";
import MsPercentage from "./ms-percentage.vue";
import MsTotalMarkFractional from "./ms-total-mark-fractional.vue";

interface Props {
    markScheme: MarkScheme;
    submissionErrorStates: SubmissionErrorStates;
    participant: {
        user: {
            id: number;
        };
    };
    tabIndex?: number;
    /**
     * Should this form input be rendered as readonly?
     */
    readonly?: boolean;
}

const props = defineProps<Props>();

const mark = defineModel<string | null>("mark", { required: true });
</script>

<template>
    <ms-letter-grade
        v-if="props.markScheme.template === 'LetterGrade'"
        v-model:mark="mark"
        :mark-scheme="props.markScheme"
        :submission-error-states="props.submissionErrorStates"
        :participant="props.participant"
        :tab-index="props.tabIndex"
        :readonly="props.readonly"
    />
    <ms-percentage
        v-else-if="props.markScheme.template === 'Percentage'"
        v-model:mark="mark"
        :submission-error-states="props.submissionErrorStates"
        :participant="props.participant"
        :tab-index="props.tabIndex"
        :readonly="props.readonly"
    />
    <ms-total-mark-fractional
        v-else-if="props.markScheme.template === 'TotalMarkFractional'"
        v-model:mark="mark"
        :mark-scheme="props.markScheme"
        :submission-error-states="props.submissionErrorStates"
        :participant="props.participant"
        :tab-index="props.tabIndex"
        :readonly="props.readonly"
    />
</template>

<style scoped></style>
