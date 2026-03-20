<script setup lang="ts">
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
    <textarea
        v-model.trim="descriptorDescription"
        :placeholder="`Descriptor for ${props.levelName} ${props.indicatorName}`"
        rows="2"
        :disabled="props.disabled"
    ></textarea>

    <!-- todo: data-descriptor-value data-descriptor-value-original data-descriptor-criterion data-descriptor-level-index data-descriptor-original -->
    <!-- todo: why tf is this a type="tel" ??? -->
    <!-- todo: usingMarkRange -->
    <input
        v-model.number="descriptorMaxValue"
        class="rb-value"
        type="tel"
        min="0"
        placeholder="Points"
        :disabled="props.disabled"
    />
</template>
