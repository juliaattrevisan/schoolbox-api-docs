<script setup lang="ts">
/**
 * The contents of a given descriptor cell (child of an indicator) in the editable rubric grid
 * The textarea will resize vertically to always fit the current content
 */

import type { RubricGridDescriptor } from "./types";

interface Props {
    disabled: boolean;
    levelName: string;
    indicatorName: string;
}
const props = defineProps<Props>();

const descriptorDescription = defineModel<RubricGridDescriptor["description"]>("description", { required: true });
const descriptorMaxValue = defineModel<RubricGridDescriptor["maxValue"]>("maxValue", { required: true });
</script>

<template>
    <!-- todo: data-descriptor-text -->
    <!-- todo: this textarea is meant to auto resize vertically to always fit the content -->
    <textarea
        v-model.trim="descriptorDescription"
        :placeholder="`Descriptor for ${props.levelName} ${props.indicatorName}`"
        rows="2"
        :disabled="props.disabled"
    ></textarea>

    <!-- todo: data-descriptor-value data-descriptor-value-original data-descriptor-criterion data-descriptor-level-index data-descriptor-original -->
    <!-- todo: usingMarkRange -->
    <input
        v-model.number="descriptorMaxValue"
        class="rb-value"
        type="number"
        min="0"
        placeholder="Points"
        :disabled="props.disabled"
    />
</template>
