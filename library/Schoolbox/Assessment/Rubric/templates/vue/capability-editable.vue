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

import locale from "@js/terminology/rosettaStone";
import { shiftArrayItem } from "@js/util/arrayUtils";
import { MARK_TYPE_VALUE_NONE } from "@schoolboxAssessment/Types/MarkType";
import IndicatorEditable from "./indicator-editable.vue";
import { MAX_CAPABILITY_LENGTH, MAX_INDICATOR_LENGTH } from "./types";
import type { RubricGridCapability, RubricGridLevel } from "./types";

interface Props {
    totalColumns: number;
    capability: RubricGridCapability;
    capabilityMarkType: number;
    levels: RubricGridLevel[];
    maxLevels: number;
    canMovePrev: boolean;
    canMoveNext: boolean;
}
const props = defineProps<Props>();

const capabilityName = defineModel<RubricGridCapability["name"]>("name", { required: true });
const capabilityMaxValue = defineModel<RubricGridCapability["maxValue"] | null>("maxValue", { required: true });
const indicators = defineModel<RubricGridCapability["indicators"]>("indicators", { required: true });

const emit = defineEmits<{ move: [direction: -1 | 1] }>();

const capabilityMarkMaxValueInputId = `rb-capability-mark-max-value-${window.Vue.useId()}`;

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
});

/**
 * Only show the max value input if there are selected indicators in the group
 * and the 'Capability Mark Type' is set to something other than 'None'
 */
const showCapabilityMaxValue = window.Vue.computed(() => {
    const hasSelectedIndicators = indicators.value.some((indicator) => indicator.hasUsage);
    return hasSelectedIndicators && props.capabilityMarkType !== MARK_TYPE_VALUE_NONE;
});

/**
 * TODO: data-rubric-sum
 */
const rubricSum = window.Vue.computed(() => {
    return 0; // TODO
});
</script>

<template>
    <!-- TODO: data-group data-sequencer-group data-groupname -->
    <tr class="rb-group-header">
        <!-- TODO: no criteria specified... etc -->
        <th :rowspan="props.capability.indicators.length + 2 /* add 2 for the header + add new indicator row */">
            <!-- todo: can't sequence when no criteria, those should stay at the end... -->
            <span class="sequencer">
                <a
                    class="icon-up-arrow"
                    :class="{ disabled: !props.canMovePrev }"
                    title="Move up"
                    :aria-disabled="!props.canMovePrev"
                    @click.prevent="props.canMovePrev && emit('move', -1)"
                ></a>
                <a
                    class="icon-down-arrow"
                    :class="{ disabled: !props.canMoveNext }"
                    title="Move down"
                    :aria-disabled="!props.canMoveNext"
                    @click.prevent="props.canMoveNext && emit('move', 1)"
                ></a>
            </span>

            <!-- todo: how on earth can groupName be null? I doubt it can be... see group-editable.twig -->
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
                v-model.trim.lazy="capabilityName"
                :maxlength="MAX_CAPABILITY_LENGTH"
                :placeholder="`${locale.rubricGroup.proper()} name`"
            ></textarea>
        </th>
    </tr>

    <indicator-editable
        v-for="(indicator, index) in indicators"
        :key="indicator.id"
        v-model:name="indicator.name"
        v-model:has-usage="indicator.hasUsage"
        :indicator="indicator"
        :levels="props.levels"
        :max-levels="props.maxLevels"
        :can-move-prev="index > 0"
        :can-move-next="index < indicators.length - 1"
        @move="(dir) => shiftArrayItem(indicators, index, dir)"
    />

    <tr class="rb-new-criterion">
        <!-- Add a new indicator (criterion) -->
        <th :colspan="totalColumns - 1 /* subtract the capability column */">
            <textarea
                :placeholder="`+ Type to add new ${locale.rubricCriterion.singular()} ${capabilityName ? `to ${capabilityName}` : ''}`"
                :maxlength="MAX_INDICATOR_LENGTH"
            ></textarea>
        </th>
    </tr>

    <!-- TODO: input for max value (data-group-max-value-row) -->
    <tr v-if="showCapabilityMaxValue">
        <th class="rb-group-summary" :colspan="totalColumns">
            <label :for="capabilityMarkMaxValueInputId">Maximum points for {{ locale.rubricGroup.singular() }}</label>
            <input
                :id="capabilityMarkMaxValueInputId"
                v-model.number="capabilityMaxValue"
                type="number"
                class="rb-value-input"
            />
            <!-- todo: data-use-criterion-values -->
            <a class="fieldAssign">Assign {{ rubricSum }} from {{ locale.rubricCriterion.plural() }}</a>
        </th>
    </tr>
</template>

<style scoped>
.rb-capability-name {
    white-space: pre-line;
}
</style>
