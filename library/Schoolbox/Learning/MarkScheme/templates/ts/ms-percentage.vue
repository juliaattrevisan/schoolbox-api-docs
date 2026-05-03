<script setup lang="ts">
/**
 * Text-input for 'percentage' markScheme
 */

import MsTextInput from "./ms-text-input.vue";
import type { SubmissionErrorStates } from "./markScheme";

const LIMIT = 100;

interface Props {
    id: string | number;
    submissionErrorStates: SubmissionErrorStates;
    tabIndex?: number;
    /**
     * Should this form input be rendered as readonly?
     */
    readonly?: boolean;
    class?: string;
    /**
     * The target for alaressValidate, if left blank then it will use the input itself
     */
    alaressValidateTarget?: string;
}

const props = defineProps<Props>();
const mark = defineModel<string | null>("mark", { required: true });
</script>

<template>
    <ms-text-input
        :id="props.id"
        v-model:mark="mark"
        :submission-error-states="props.submissionErrorStates"
        :class="props.class"
        :tab-index="props.tabIndex"
        :placeholder="props.readonly ? '(no mark given)' : 'Percentage mark'"
        :limit="LIMIT"
        :max-length="48"
        input-title="Percentage"
        :readonly="props.readonly"
        :alaress-validate-target="props.alaressValidateTarget"
        :alaress-validate-options="{
            rules: {
                mark: {
                    withinPercentageRange: true,
                },
            },
            messages: {
                mark: {
                    withinPercentageRange: `The grade must be between 0 and ${LIMIT}.`,
                },
            },
        }"
    />
</template>
