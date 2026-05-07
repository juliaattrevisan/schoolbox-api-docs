<script setup lang="ts">
/**
 * This is the equivalent of resubmissionRequired.twig
 * Renders an input where teachers can check whether the learner must resubmit
 */

import type { StyleValue } from "vue";

interface Props {
    identifier: number;
    readonly?: boolean;
    tabIndex?: number;
    style?: StyleValue;
}

const props = defineProps<Props>();
const resubmissionRequired = defineModel<boolean>("resubmission-required", { required: true });
</script>

<template>
    <div class="resubmission-required l-flex l-flex--align-center l-gap-s">
        <input
            :id="`resubmissionRequired-${identifier}`"
            v-model="resubmissionRequired"
            type="checkbox"
            name="resubmissionRequired"
            :value="1"
            :tabindex="props.tabIndex"
            :readonly="props.readonly"
        />
        <label :for="`resubmissionRequired-${identifier}`" class="checkbox-label" :style="props.style">
            Resubmission required
        </label>
    </div>
</template>

<style scoped></style>
