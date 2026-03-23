<script setup lang="ts">
/**
 * The contents of a given descriptor cell (child of an indicator) in the editable rubric grid
 * The textarea will resize vertically to always fit the current content
 */

import { MAX_DESCRIPTOR_LENGTH, type RubricGridDescriptor } from "./types";

interface Props {
    levelName: string;
    indicatorName: string;
}
const props = defineProps<Props>();

const indicatorEnabled = defineModel<boolean>("indicatorEnabled", { required: true });
const descriptorDescription = defineModel<RubricGridDescriptor["description"]>("description", { required: true });

const descriptorMaxValueModel = defineModel<RubricGridDescriptor["maxValue"] | null>("maxValue", { required: true });
const descriptorMaxValue = window.Vue.computed({
    get: () => descriptorMaxValueModel.value,
    set: (value: string | number | null) => {
        // clearing an input will attempt to set it to "", treat that as null instead
        const isValid = typeof value === "number" && !Number.isNaN(value);
        descriptorMaxValueModel.value = isValid ? value : null;
    },
});

const textareaRef = window.Vue.useTemplateRef<HTMLTextAreaElement>("textareaRef");

/**
 * When the cell is clicked and the indicator this is part of is disabled,
 * enable the indicator and focus the textarea
 */
async function onCellClick(): Promise<void> {
    if (indicatorEnabled.value) {
        return; // already enabled, don't want to change the focus accidentally
    }

    indicatorEnabled.value = true;
    await window.Vue.nextTick();
    textareaRef.value?.focus();
}
</script>

<template>
    <td :class="!indicatorEnabled ? 'hide-v' : ''" @click="onCellClick">
        <!-- todo: data-descriptor-text -->
        <!-- todo: this textarea is meant to auto resize vertically to always fit the content -->
        <textarea
            ref="textareaRef"
            v-model.trim="descriptorDescription"
            :placeholder="`Descriptor for ${props.levelName} ${props.indicatorName}`"
            rows="2"
            :maxlength="MAX_DESCRIPTOR_LENGTH"
            :disabled="!indicatorEnabled"
        ></textarea>

        <!-- todo: data-descriptor-value data-descriptor-value-original data-descriptor-criterion data-descriptor-level-index data-descriptor-original -->
        <!-- todo: usingMarkRange -->
        <input
            v-model.number="descriptorMaxValue"
            class="rb-value rb-value-input"
            type="number"
            min="0"
            placeholder="Points"
            :disabled="!indicatorEnabled"
        />
    </td>
</template>
