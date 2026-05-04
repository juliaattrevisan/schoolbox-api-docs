<script setup lang="ts">
/**
 * Add rows in the markable rubric grid for a specific capability
 * (also known as a "group" in the past)
 */

import locale from "@js/terminology/rosettaStone";
import MarkScheme from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/mark-scheme.vue";
import type { MarkScheme as CapabilityMarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { RubricGridCapability, RubricGridLevel, RubricMarkedGridCapability } from "./types";
import RmGridIndicator from "./rm-grid-indicator.vue";

interface Props {
    capability: RubricGridCapability;
    selfAssessmentCapabilityMark: RubricMarkedGridCapability | null;
    capabilityMarkScheme: CapabilityMarkScheme;
    levels: RubricGridLevel[];
    participantId: UserAuthor["id"] | null;
    showSummaryCapability: boolean;
}
const props = defineProps<Props>();
const capabilityMark = defineModel<RubricMarkedGridCapability>("capabilityMark", { required: true });

const componentId = window.Vue.useId();

const showSummaryForThisCapability = window.Vue.computed(() => {
    return props.showSummaryCapability && props.capability.showCapabilityMark;
});

/** All indicators, with their associated mark data */
const indicators = window.Vue.computed(() => {
    return props.capability.indicators.map((indicator) => {
        const indicatorMark = capabilityMark.value.indicators.find((cap) => cap.id === indicator.id) ?? null;
        const selfAssessmentIndicatorMark =
            props.selfAssessmentCapabilityMark?.indicators.find((cap) => cap.id === indicator.id) ?? null;

        // if an indicator exists in the rubric, it must always exist in the marked grid
        if (!indicatorMark) {
            throw new Error(`Unable to find indicator mark for indicator "${indicator.id}"`);
        }

        return {
            indicator: indicator,
            mark: indicatorMark,
            selfAssessmentMark: selfAssessmentIndicatorMark,
        };
    });
});
</script>

<template>
    <tr v-if="indicators.length">
        <th
            class="rb-group-name markdown"
            :rowspan="indicators.length + (showSummaryForThisCapability ? 2 : 1) /* header+summary */"
        >
            <span>
                <!-- TODO: should be scrubbed (markdown) and output as html -->
                {{ props.capability.name }}
            </span>
        </th>
    </tr>

    <rm-grid-indicator
        v-for="indicator in indicators"
        :key="indicator.indicator.id"
        v-model:indicator-mark="indicator.mark"
        :indicator="indicator.indicator"
        :self-assessment-indicator-mark="indicator.selfAssessmentMark"
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
                <label :for="`capability-${props.capability.id}-mark-${componentId}`">
                    {{ locale.rubricGroup.proper() }} mark
                </label>

                <!-- TODO: data-group-mark -->
                <mark-scheme
                    :id="`capability-${props.capability.id}-mark-${componentId}`"
                    v-model:mark="capabilityMark.value"
                    :mark-scheme="props.capabilityMarkScheme"
                    :submission-error-states="[] /** no error states here */"
                    :limit="capability.maxValue ?? undefined"
                    class="rb-value-input groupMark"
                />

                <template v-if="props.capabilityMarkScheme.template === 'Percentage'"> % </template>
                <template v-else-if="props.capabilityMarkScheme.template === 'TotalMarkFractional'">
                    / {{ capability.maxValue }}
                </template>
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
                {{ props.selfAssessmentCapabilityMark.humanReadableMark }}
                given in
                {{ locale.selfAssessment.singular() }}
            </p>
        </th>
    </tr>
</template>
