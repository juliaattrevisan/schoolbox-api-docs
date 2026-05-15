<script setup lang="ts">
/**
 * Add rows in the editable rubric grid for a specific capability
 * (also known as a "group" in the past)
 *
 * The capability itself can be edited from the leftmost column,
 * it can have multiple indicators, each indicator has its own row inline with the capability.
 * There are two additional rows:
 * - A row inline with the capability name for adding a new indicator
 * - A full width row for the maximum capability points, if the capability mark type is set
 */

import type { Ref } from "vue";
import locale from "@js/terminology/rosettaStone";
import { shiftArrayItem } from "@js/util/arrayUtils";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import RbGridIndicator from "./rb-grid-indicator.vue";
import { MAX_CAPABILITY_LENGTH, MAX_INDICATOR_LENGTH } from "./types";
import type { RubricGridCapability, RubricGridLevel } from "./types";

interface Props {
    capability: RubricGridCapability;
    levels: RubricGridLevel[];
    totalColumns: number;
    isCreating: boolean;
    maxLevels: number;
    capabilityMarkType: MarkTypeSlug;
    useMarkRange: boolean;
    canMovePrev: boolean;
    canMoveNext: boolean;
}
const props = defineProps<Props>();

const capabilityName = defineModel<RubricGridCapability["name"]>("name", { required: true });
const indicators = defineModel<RubricGridCapability["indicators"]>("indicators", { required: true });

const capabilityMaxValueModel = defineModel<RubricGridCapability["maxValue"] | null>("maxValue", { required: true });

const emit = defineEmits<{
    move: [direction: -1 | 1];
    addIndicatorKeydown: [event: KeyboardEvent, nameRef: Ref<string, string>];
}>();

const componentId = window.Vue.useId();

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
});

/* only add maxlength to fields that actually change. to then only validate those fields characters count */
const capabilityNameHasChanged = window.Vue.ref(false);

const newIndicatorName = window.Vue.ref("");

/**
 * When the return key is pressed, add a new indicator to the capability
 * (this needs to exist as the ref can't be passed directly when inside <template>)
 * @param event the keydown event
 */
function onAddIndicatorKeydown(event: KeyboardEvent): void {
    emit("addIndicatorKeydown", event, newIndicatorName);
}

/**
 * Only show the max value input if there are selected indicators in the group
 * and the 'Capability Mark Type' is set to something other than 'None'
 */
const showCapabilityMaxValue = window.Vue.computed(() => {
    const hasSelectedIndicators = indicators.value.some((indicator) => indicator.hasUsage);
    return hasSelectedIndicators && props.capabilityMarkType !== MarkTypeSlug.NONE;
});

/**
 * Calculate the current max available points for this capability
 * based on the value of all indicators in the capability
 */
const derivedMaxValue = window.Vue.computed(() => {
    let indicatorMaxSum = 0;
    for (const indicator of indicators.value) {
        if (!indicator.hasUsage) {
            // Not checked? Don't count this indicator towards the total
            continue;
        }

        // Find the highest valued descriptor for this indicator
        let indicatorMax = 0;
        for (const descriptor of indicator.descriptors) {
            const value = descriptor.maxValue ?? 0;
            if (value > indicatorMax) {
                indicatorMax = value;
            }
        }

        indicatorMaxSum += indicatorMax;
    }

    return indicatorMaxSum;
});

const capabilityMaxValue = window.Vue.computed({
    get: () => capabilityMaxValueModel.value ?? derivedMaxValue.value,
    set: (value: string | number | null) => {
        // clearing an input will attempt to set it to "", treat that as null instead
        const isValid = typeof value === "number" && !Number.isNaN(value);
        capabilityMaxValueModel.value = isValid ? value : null;
    },
});

/**
 * Keep track of if the max value field has been customised or not in order to
 * know whether to override it when the derived max value changes
 */
const isCustomised = window.Vue.ref(false);
window.Vue.watch(derivedMaxValue, (newValue) => {
    if (isCustomised.value) return; // don't sync if customised
    capabilityMaxValue.value = newValue;
});

window.Vue.onMounted(() => {
    // if the capability max value is initially set and differs from the derived max value,
    // then the field should be considered customised on load
    if (capabilityMaxValue.value !== null) {
        isCustomised.value = capabilityMaxValue.value !== derivedMaxValue.value;
    }
});
</script>

<template>
    <tr class="rb-group-header">
        <th :rowspan="props.capability.indicators.length + 2 /* add 2 for the header + add new indicator row */">
            <!-- todo: can't sequence when no criteria, those should stay at the end... -->
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

            <label v-if="props.capability.inUseExcludingAssessment" class="rb-capability-name">
                {{ capabilityName }}
                <span
                    data-tooltip
                    class="hint has-tip tip-right"
                    data-width="200"
                    :title="`This ${locale.rubricGroup.singular()} cannot be modified because it contains one or more ${locale.rubricCriterion.plural()} that are in use elsewhere`"
                >
                    ?
                </span>
            </label>

            <textarea
                v-else
                :id="`rb-capability-name-textarea-${componentId}`"
                v-model.trim.lazy="capabilityName"
                :maxlength="capabilityNameHasChanged ? MAX_CAPABILITY_LENGTH : undefined"
                :placeholder="`${locale.rubricGroup.proper()} name`"
                @change.once="capabilityNameHasChanged = true"
            ></textarea>
        </th>
    </tr>

    <rb-grid-indicator
        v-for="(indicator, index) in indicators"
        :key="indicator.id"
        v-model:name="indicator.name"
        v-model:has-usage="indicator.hasUsage"
        :capability-id="props.capability.id"
        :indicator="indicator"
        :levels="props.levels"
        :max-levels="props.maxLevels"
        :use-mark-range="props.useMarkRange"
        :can-move-prev="index > 0"
        :can-move-next="index < indicators.length - 1"
        @move="(dir) => shiftArrayItem(indicators, index, dir)"
    />

    <tr class="rb-new-criterion">
        <!-- Add a new indicator (criterion) -->
        <th :colspan="totalColumns - 1 /* subtract the capability column */">
            <textarea
                v-model.trim="newIndicatorName"
                :placeholder="`+ Type to add new ${locale.rubricCriterion.singular()} ${capabilityName ? `to ${capabilityName}` : ''}`"
                :maxlength="MAX_INDICATOR_LENGTH"
                :disabled="isCreating || undefined"
                @keydown="onAddIndicatorKeydown"
            ></textarea>
        </th>
    </tr>

    <tr v-if="showCapabilityMaxValue">
        <!-- input for max value -->
        <th class="rb-group-summary" :colspan="totalColumns">
            <label :for="`rb-capability-mark-max-value-${componentId}`">
                Maximum points for {{ locale.rubricGroup.singular() }}
            </label>
            <input
                :id="`rb-capability-mark-max-value-${componentId}`"
                v-model.number="capabilityMaxValue"
                type="number"
                class="rb-value-input"
                required
                data-group-max-value
                @change.self="isCustomised = true"
            />
            <a
                v-if="capabilityMaxValue != derivedMaxValue"
                class="fieldAssign"
                href="#"
                data-use-criterion-values
                @click.prevent="
                    capabilityMaxValue = derivedMaxValue;
                    isCustomised = false;
                "
            >
                Assign {{ derivedMaxValue }} from {{ locale.rubricCriterion.plural() }}
            </a>
        </th>
    </tr>
</template>

<style scoped>
.rb-capability-name {
    white-space: pre-line;
}
</style>
