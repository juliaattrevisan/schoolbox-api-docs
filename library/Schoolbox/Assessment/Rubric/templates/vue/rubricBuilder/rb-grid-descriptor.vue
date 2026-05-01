<script setup lang="ts">
/**
 * The contents of a given descriptor cell (child of an indicator) in the editable rubric grid
 * The textarea will resize vertically to always fit the current content
 */

import { MAX_DESCRIPTOR_LENGTH } from "./types";
import type { RubricGridDescriptor } from "./types";

interface Props {
    descriptorIndex: number;
    levelName: string;
    indicatorName: string;
    useMarkRange: boolean;
    textareaHeight?: string;
}
const props = defineProps<Props>();

const emit = defineEmits(["descriptionInput"]);

const componentId = window.Vue.useId();

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

/* only add maxlength to fields that actually change. to then only validate those fields characters count */
const descriptionHasChanged = window.Vue.ref(false);

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

defineExpose({ textareaRef });
</script>

<template>
    <td
        :class="!indicatorEnabled ? 'hide-v' : ''"
        :data-test="`rubric-descriptor-cell-${props.descriptorIndex}`"
        data-descriptor
        @click="onCellClick"
    >
        <textarea
            :id="`rb-descriptor-textarea-${componentId}`"
            ref="textareaRef"
            v-model.trim="descriptorDescription"
            :placeholder="`Descriptor for ${props.levelName} ${props.indicatorName}`"
            rows="2"
            :maxlength="descriptionHasChanged ? MAX_DESCRIPTOR_LENGTH : undefined"
            :disabled="!indicatorEnabled || undefined"
            :style="{ height: props.textareaHeight }"
            :name="`rb-descriptor-textarea-${componentId}` /* alaressValidate hates this and applies the rule for the below input to the textarea if this doesn't have a name??? */"
            @change.once="descriptionHasChanged = true"
            @input="emit('descriptionInput')"
        ></textarea>

        <input
            :id="`rb-descriptor-max-value-${componentId}`"
            v-model.number="descriptorMaxValue"
            class="rb-value rb-value-input"
            type="number"
            min="0"
            :placeholder="useMarkRange ? 'Maximum points' : 'Points'"
            :disabled="!indicatorEnabled || undefined"
            :data-descriptor-value="indicatorEnabled || undefined"
            data-test="rubric-descriptor-max-value"
        />
    </td>
</template>
