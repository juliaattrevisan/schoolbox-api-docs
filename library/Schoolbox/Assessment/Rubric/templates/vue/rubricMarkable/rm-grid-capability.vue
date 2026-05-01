<script setup lang="ts">
/**
 * Add rows in the markable rubric grid for a specific capability
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

const componentId = window.Vue.useId();

const showSummaryForThisCapability = window.Vue.computed(() => {
    return props.showSummaryCapability && props.capability.showCapabilityMark;
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
            <span>
                <!-- TODO: should be scrubbed (markdown) and output as html -->
                {{ props.capability.name }}
            </span>
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

    <tr v-if="showSummaryForThisCapability">
        <th class="rb-group-summary" :colspan="props.levels.length + 1 /* descriptors + indicator column */">
            <!-- TODO: this span wrapper is probably unnecessary and solely for the old data attrs -->
            <!-- TODO: data-group-summary="{{ rubricId }}-{{ capabilityId }}" -->
            <!-- TODO: data-initial-configured-mark="{{ capabilityMark ? capabilityMark.value : null }}" -->
            <!-- TODO: data-scaled-mark="{{ capabilityScaledMark }}" -->
            <!-- TODO: data-max="{{ capabilityMaxValue }}" -->
            <!-- TODO: data-row-max-sum="{{ capabilityMaxFromIndicators }}" -->
            <!-- TODO: data-group-id="{{ capabilityId }}" -->
            <!-- TODO: data-rubric-id="{{ rubricGrid.id }}" -->
            <!-- TODO: data-mark-type="{{ capabilityMarkType.mode }}" -->
            <span>
                <label :for="`capability-${$props.capability.id}-mark-${componentId}`">
                    {{ locale.rubricGroup.proper() }} mark
                </label>

                <!-- TODO: mark input -->
            </span>

            <!-- TODO: data-use-descriptor-values -->
            <!-- TODO: style="display: none" conditionally & by default -->
            <a class="fieldAssign">
                <!-- TODO: these two spans are a v-if/v-else probably -->
                <!-- TODO: data-descriptor-assign="has-selected-descriptor" -->
                <span>
                    Assign
                    <!-- TODO: conditionally render this span instead? -->
                    <!-- TODO: data-group-descriptor-sum -->
                    <span class="descriptor-sum">
                        <!-- TODO capabilityMark.indicatorMarkSumScaled -->
                    </span>
                    from {{ locale.rubricCriterion.plural() }}
                </span>
                <!-- TODO: data-descriptor-assign="no-selected-descriptor" -->
                <span> Remove {{ locale.grades.singular() }} based on {{ locale.rubricCriterion.plural() }} </span>
            </a>

            <!-- Self-Assessment -->
            <p v-if="props.selfAssessmentCapabilityMark?.humanReadableMark" class="meta">
                ({{ props.selfAssessmentCapabilityMark.humanReadableMark }}
                given in
                {{ locale.selfAssessment.singular() }})
            </p>
        </th>
    </tr>
</template>
