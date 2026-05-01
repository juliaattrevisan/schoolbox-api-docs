<script setup lang="ts">
/**
 * Add rows in the readonly rubric grid for a specific capability
 * (also known as a "group" in the past)
 */

import locale from "@js/terminology/rosettaStone";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type {
    RubricGridCapability,
    RubricGridLevel,
    RubricMarkedGridCapability,
    RubricMarkedGridIndicator,
} from "./types";
import RmGridIndicator from "./rm-grid-indicator.vue";

interface Props {
    capability: RubricGridCapability;
    capabilityMark: RubricMarkedGridCapability | null;
    selfAssessmentCapabilityMark: RubricMarkedGridCapability | null;
    levels: RubricGridLevel[];
    participantId: UserAuthor["id"];
    showSummaryCapability: boolean;
}
const props = defineProps<Props>();

const showSummaryForThisCapability = window.Vue.computed(() => {
    if (props.selfAssessmentCapabilityMark && props.selfAssessmentCapabilityMark.humanReadableMark !== null) {
        return true;
    }
    return props.showSummaryCapability && props.capabilityMark && props.capabilityMark.humanReadableMark !== null;
});

/**
 * Find an indicator mark for a specific indicator in a RubricMarkedGridCapability
 * @param indicatorId the id of the indicator to find the mark for
 * @param markedGridCapability the RubricMarkedGridCapability to find the mark in
 * @returns the indicator mark, or null if not found
 */
function findIndicatorMark(
    indicatorId: number,
    markedGridCapability: RubricMarkedGridCapability | null,
): RubricMarkedGridIndicator | null {
    if (!markedGridCapability) {
        return null;
    }
    return markedGridCapability.indicators.find((indicator) => indicator.id === indicatorId) ?? null;
}
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

    <rm-grid-indicator
        v-for="indicator in props.capability.indicators"
        :key="indicator.id"
        :indicator="indicator"
        :indicator-mark="findIndicatorMark(indicator.id, props.capabilityMark)"
        :self-assessment-indicator-mark="findIndicatorMark(indicator.id, props.selfAssessmentCapabilityMark)"
        :levels="props.levels"
        :participant-id="props.participantId"
    />

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
                ({{ props.selfAssessmentCapabilityMark.humanReadableMark }}
                given in
                {{ locale.selfAssessment.singular() }})
            </template>
        </th>
    </tr>
</template>
