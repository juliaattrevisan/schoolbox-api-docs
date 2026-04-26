<script setup lang="ts">
/**
 * Add rows in the readonly rubric grid for a specific indicator (child of a capability)
 * (also known as a "criterion" in the past)
 */

import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type {
    RubricGridIndicator,
    RubricGridLevel,
    RubricMarkedGridDescriptor,
    RubricMarkedGridIndicator,
} from "./types";
import RrGridDescriptor from "./rr-grid-descriptor.vue";

interface Props {
    indicator: RubricGridIndicator;
    indicatorMark: RubricMarkedGridIndicator | null;
    selfAssessmentIndicatorMark: RubricMarkedGridIndicator | null;
    levels: RubricGridLevel[];
    participantId: UserAuthor["id"];
    showDescriptorValue?: boolean;
}
const props = defineProps<Props>();

/**
 * Find a descriptor mark for a specific descriptor in a RubricMarkedGridIndicator
 * @param descriptorIndex the index of the descriptor to find the mark for
 * @param markedGridIndicator the RubricMarkedGridIndicator to find the mark in
 * @returns the descriptor mark, or null if not found
 */
function findDescriptorMark(
    descriptorIndex: number,
    markedGridIndicator: RubricMarkedGridIndicator | null,
): RubricMarkedGridDescriptor | null {
    if (!markedGridIndicator) {
        return null;
    }
    return markedGridIndicator.descriptors[descriptorIndex] ?? null;
}
</script>

<template>
    <tr>
        <!-- TODO: should be scrubbed (markdown) and output as html -->
        <th class="rb-criterion-name markdown">{{ props.indicator.name }}</th>

        <template v-for="(level, index) in props.levels" :key="level.id">
            <rr-grid-descriptor
                v-if="props.indicator.descriptors[index] !== null"
                :descriptor="props.indicator.descriptors[index]"
                :indicator-mark="props.indicatorMark"
                :descriptor-mark="findDescriptorMark(index, props.indicatorMark)"
                :self-assessment-descriptor-mark="findDescriptorMark(index, props.selfAssessmentIndicatorMark)"
                :level="level"
                :participant-id="props.participantId"
                :show-descriptor-value="props.showDescriptorValue"
            />
            <!-- TODO: for steve's sanity, figure out if there's a good way to make the lines continuous across cells -->
            <td v-else class="rb-blank hide-for-medium-down">&nbsp;</td>
        </template>
    </tr>
</template>
