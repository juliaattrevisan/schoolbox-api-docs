<script setup lang="ts">
/**
 * Add rows in the readonly rubric grid for a specific capability
 * (also known as a "group" in the past)
 */

import locale from "@js/terminology/rosettaStone";
import type { RubricGridCapability, RubricGridLevel, RubricMarkedGridCapability } from "./types";

interface Props {
    capability: RubricGridCapability;
    capabilityMark: RubricMarkedGridCapability | null;
    selfAssessmentCapabilityMark: RubricMarkedGridCapability | null;
    levels: RubricGridLevel[];
    showSummaryCapability: boolean;
}
const props = defineProps<Props>();

const showSummaryForThisCapability = window.Vue.computed(() => {
    if (props.selfAssessmentCapabilityMark && props.selfAssessmentCapabilityMark.humanReadableMark !== null) {
        return true;
    }
    return props.showSummaryCapability && props.capabilityMark && props.capabilityMark.humanReadableMark !== null;
});
</script>

<template>
    <tr v-if="props.capability.indicators.length">
        <th
            class="rb-group-name markdown"
            :rowspan="props.capability.indicators.length + (showSummaryForThisCapability ? 2 : 1) /* header+summary */"
        >
            <!-- TODO: should be scrubbed (markdown) and output as html -->
            {{ props.capability.name }}
        </th>
    </tr>

    <!-- <rb-grid-indicator
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
    /> -->

    <!-- Capability mark: needs to be rendered in human-readable form, use the assessment's mark scheme to do so -->
    <tr v-if="showSummaryForThisCapability">
        <!--
        Capability Mark spans the following columns:
            + N descriptors (equivalent to N levels)
            + Indicator cell (always 1 cell)
            = N + 1 cells colspan
        (It does not span the capability cell itself)
        -->
        <th class="rb-group-summary" :colspan="props.levels.length + 1 /* descriptors + indicator column */">
            {{ props.capabilityMark?.humanReadableMark ?? "" }}

            <!-- Self-Assessment -->
            <template v-if="props.selfAssessmentCapabilityMark?.humanReadableMark">
                {{ props.selfAssessmentCapabilityMark.humanReadableMark }}
                given in
                {{ locale.selfAssessment.singular() }}
            </template>
        </th>
    </tr>
</template>
