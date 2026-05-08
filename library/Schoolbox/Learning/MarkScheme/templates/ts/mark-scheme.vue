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
    /** A unique identifier for this mark scheme input */
    id: string | number;
    /** The name to use for this mark scheme's input element */
    name?: string;
    markScheme: MarkScheme;
    submissionErrorStates: SubmissionErrorStates;
    class?: string;
    tabIndex?: number;
    /**
     * Should this form input be rendered as readonly?
     */
    readonly?: boolean;
    /**
     * Optional precedence: used for quiz only
     * Maximum marks available per attempt, indexed by user id
     */
    limit?: number;
    /**
     * Optional custom error placement for alaressValidate errors
     * Should be a selector for an element for the error to be placed within, all inner content gets replaced
     */
    errorPlaceholderSelector?: string;
    /**
     * If false, prevents mark from updating until the value is valid
     * If true, the value of mark will update regardless of validation, but validation messages will still display as normal
     */
    allowInvalidMark?: boolean;
}

const props = defineProps<Props>();

const mark = defineModel<string | null>("mark", { required: true });
</script>

<template>
    <ms-letter-grade
        v-if="props.markScheme.template === 'LetterGrade'"
        :id="props.id"
        v-model:mark="mark"
        :name="props.name"
        :mark-scheme="props.markScheme"
        :submission-error-states="props.submissionErrorStates"
        :class="props.class"
        :tab-index="props.tabIndex"
        :readonly="props.readonly"
        :error-placeholder-selector="props.errorPlaceholderSelector"
        :allow-invalid-mark="props.allowInvalidMark"
    />
    <ms-percentage
        v-else-if="props.markScheme.template === 'Percentage'"
        :id="props.id"
        v-model:mark="mark"
        :name="props.name"
        :submission-error-states="props.submissionErrorStates"
        :class="props.class"
        :tab-index="props.tabIndex"
        :readonly="props.readonly"
        :error-placeholder-selector="props.errorPlaceholderSelector"
        :allow-invalid-mark="props.allowInvalidMark"
    />
    <ms-total-mark-fractional
        v-else-if="props.markScheme.template === 'TotalMarkFractional'"
        :id="props.id"
        v-model:mark="mark"
        :name="props.name"
        :mark-scheme="props.markScheme"
        :submission-error-states="props.submissionErrorStates"
        :class="props.class"
        :tab-index="props.tabIndex"
        :readonly="props.readonly"
        :limit="props.limit"
        :error-placeholder-selector="props.errorPlaceholderSelector"
        :allow-invalid-mark="props.allowInvalidMark"
    />
</template>

<style scoped></style>
