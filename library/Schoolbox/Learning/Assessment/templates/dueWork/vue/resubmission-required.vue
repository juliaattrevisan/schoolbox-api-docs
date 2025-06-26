<script setup lang="ts">
/**
 * This is the equivalent of resubmissionRequired.twig
 * Renders an input where teachers can check whether the learner must resubmit
 */

interface Props {
    identifier: number;
    readonly?: boolean;
    tabIndex?: number;
}
const props = defineProps<Props>();
const resubmissionRequired = defineModel<boolean>("resubmission-required", { required: true });
</script>

<template>
    <input
        :id="`resubmissionRequired-${identifier}`"
        v-model="resubmissionRequired"
        type="checkbox"
        name="resubmissionRequired"
        :value="1"
        :tabindex="props.tabIndex"
        :readonly="props.readonly"
    />
    <label :for="`resubmissionRequired-${identifier}`" class="checkbox-label"> Resubmission required </label>
</template>

<style scoped></style>
