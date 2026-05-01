<script setup lang="ts">
/**
 * Add rows in the readonly rubric grid for a specific indicator (child of a capability)
 * (also known as a "criterion" in the past)
 */

import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { RubricGridIndicator, RubricGridLevel, RubricMarkedGridIndicator } from "./types";
import RrGridDescriptor from "./rr-grid-descriptor.vue";

interface Props {
    indicator: RubricGridIndicator;
    indicatorMark: RubricMarkedGridIndicator | null;
    selfAssessmentIndicatorMark: RubricMarkedGridIndicator | null;
    levels: RubricGridLevel[];
    participantId: UserAuthor["id"];
}
const props = defineProps<Props>();
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
                :self-assessment-indicator-mark="props.selfAssessmentIndicatorMark"
                :level="level"
                :participant-id="props.participantId"
            />
            <td v-else class="rb-blank hide-for-medium-down">&nbsp;</td>
        </template>
    </tr>
</template>
