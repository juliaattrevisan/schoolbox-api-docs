<script setup lang="ts">
/**
 * Add a row in the editable rubric grid for a specific indicator (child of a capability)
 * (also known as a "criterion" in the past)
 *
 * The indicator itself can be edited from the left column (which will be next
 * to a capability already). it can have multiple descriptors, each descriptor has
 * its own column corresponding to the associated level.
 *
 * There is an additional blank column at the end matching the "+ Level" column
 * in the header if it exists
 */

import { useTemplateRefsList } from "@vueuse/core";
import locale from "@js/terminology/rosettaStone";
import RbGridDescriptor from "./rb-grid-descriptor.vue";
import { MAX_INDICATOR_LENGTH } from "./builderTypes";
import type { RubricGridCapability, RubricGridIndicator, RubricGridLevel } from "./builderTypes";

interface Props {
    capabilityId: RubricGridCapability["id"];
    indicator: RubricGridIndicator;
    levels: RubricGridLevel[];
    maxLevels: number;
    useMarkRange: boolean;
    canMovePrev: boolean;
    canMoveNext: boolean;
}
const props = defineProps<Props>();

const indicatorName = defineModel<RubricGridIndicator["name"]>("name", { required: true });
const hasUsage = defineModel<boolean>("hasUsage", { required: true });

const componentId = window.Vue.useId();

const emit = defineEmits<{ move: [direction: -1 | 1] }>();

/* only add maxlength to fields that actually change. to then only validate those fields characters count */
const indicatorNameHasChanged = window.Vue.ref(false);

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
});

const descriptorTextareaHeight = window.Vue.ref<string>("auto");

const descriptorRefs = useTemplateRefsList<InstanceType<typeof RbGridDescriptor>>();

/**
 * Update the height of the descriptor textareas to fit the content
 * but also be consistent across all descriptors in the same row
 */
async function updateDescriptorHeight(): Promise<void> {
    if (!descriptorRefs.value) {
        return;
    }

    // reset to auto to allow shrinking when content is removed
    descriptorTextareaHeight.value = "auto";
    await window.Vue.nextTick();

    let maxHeight = 0;
    const textareas: HTMLTextAreaElement[] = descriptorRefs.value
        .map((ref) => ref.textareaRef)
        .filter((ref) => ref !== null);

    for (const textarea of textareas) {
        const { borderTopWidth, borderBottomWidth } = window.getComputedStyle(textarea);

        const textareaHeight = textarea.scrollHeight + parseFloat(borderTopWidth) + parseFloat(borderBottomWidth);
        maxHeight = Math.max(maxHeight, textareaHeight);
    }

    descriptorTextareaHeight.value = `${maxHeight}px`;
}

window.Vue.onMounted(() => {
    setTimeout(() => void updateDescriptorHeight(), 0);
});
</script>

<template>
    <tr data-indicator-row :data-test="`rubric-indicator-row-${props.capabilityId}-${props.indicator.id}`">
        <th>
            <span class="sequencer">
                <a
                    class="sbx-icon-only-button no-text"
                    :class="{ disabled: !props.canMovePrev }"
                    title="Move up"
                    aria-label="Move up"
                    :aria-disabled="!props.canMovePrev"
                    @click.prevent="props.canMovePrev && emit('move', -1)"
                >
                    <sbx-icon name="up-arrow" />
                </a>
                <a
                    class="sbx-icon-only-button no-text"
                    :class="{ disabled: !props.canMoveNext }"
                    title="Move down"
                    aria-label="Move down"
                    :aria-disabled="!props.canMoveNext"
                    @click.prevent="props.canMoveNext && emit('move', 1)"
                >
                    <sbx-icon name="down-arrow" />
                </a>
            </span>

            <div class="l-flex l-flex--align-start l-gap-s">
                <input
                    :id="`rb-indicator-${componentId}`"
                    v-model="hasUsage"
                    type="checkbox"
                    value="1"
                    data-criterion-name
                />
                <label
                    v-if="props.indicator.inUseExcludingAssessment"
                    class="checkbox-label rb-criterion rb-readonly rb-indicator-name"
                    :for="`rb-indicator-${componentId}`"
                    data-test="rubric-indicator-toggle"
                >
                    {{ indicatorName }}
                    <span
                        data-tooltip
                        class="hint has-tip tip-right f-no-margins"
                        data-width="200"
                        :title="`This ${locale.rubricCriterion.singular()} cannot be modified because it is in use elsewhere`"
                    >
                        ?
                    </span>
                </label>
                <label
                    v-else
                    class="checkbox-label rb-criterion rb-editable"
                    :for="`rb-indicator-${componentId}`"
                    data-test="rubric-indicator-toggle"
                >
                    <textarea
                        :id="`rb-indicator-textarea-${componentId}`"
                        v-model.trim.lazy="indicatorName"
                        class="rb-indicator-textarea"
                        :maxlength="indicatorNameHasChanged ? MAX_INDICATOR_LENGTH : undefined"
                        :placeholder="`${locale.rubricCriterion.proper()} name`"
                        @change.once="indicatorNameHasChanged = true"
                    ></textarea>
                </label>
            </div>
        </th>

        <rb-grid-descriptor
            v-for="(descriptor, levelIndex) in props.indicator.descriptors"
            :key="descriptor.id"
            :ref="descriptorRefs.set"
            v-model:indicator-enabled="hasUsage"
            v-model:description="descriptor.description"
            v-model:max-value="descriptor.maxValue"
            :descriptor-index="levelIndex"
            :level-name="props.levels[levelIndex].name"
            :indicator-name="props.indicator.name"
            :use-mark-range="props.useMarkRange"
            :textarea-height="descriptorTextareaHeight"
            @description-input="updateDescriptorHeight"
        />

        <td
            v-if="props.levels.length < props.maxLevels"
            class="hide-for-medium-down"
            :class="hasUsage ? '' : 'hide-v'"
            @click="hasUsage = true"
        >
            &nbsp;
        </td>
    </tr>
</template>

<style scoped>
.rb-indicator-name {
    white-space: pre-line;
}
</style>
