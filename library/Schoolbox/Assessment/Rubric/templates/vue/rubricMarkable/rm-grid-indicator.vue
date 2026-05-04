<script setup lang="ts">
/**
 * Add rows in the markable rubric grid for a specific indicator (child of a capability)
 * (also known as a "criterion" in the past)
 */

import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { RubricGridIndicator, RubricGridLevel, RubricMarkedGridIndicator } from "./types";
import RmGridDescriptor from "./rm-grid-descriptor.vue";

interface Props {
    indicator: RubricGridIndicator;
    indicatorMark: RubricMarkedGridIndicator | null;
    selfAssessmentIndicatorMark: RubricMarkedGridIndicator | null;
    levels: RubricGridLevel[];
    participantId: UserAuthor["id"] | null;
}
const props = defineProps<Props>();

// TODO: this should actually be a model, also is it possible for props.indicatorMark to be null? it really shouldn't be for this to work properly...
const indicatorMark = window.Vue.ref<RubricMarkedGridIndicator>(
    props.indicatorMark ?? {
        id: props.indicator.id,
        descriptorId: null,
        value: null,
    },
);
</script>

<template>
    <!-- TODO: data-group="{{ rubricId }}-{{ groupUsage.capability.id }}" -->
    <!-- TODO: data-capability-id="{{ groupUsage.capability.id }}" -->
    <!-- TODO: data-indicator-id="{{ indicatorId }}" -->
    <tr class="rb-indicator-row">
        <!-- TODO: should be scrubbed (markdown) and output as html -->
        <th class="rb-criterion-name markdown">{{ props.indicator.name }}</th>

        <template v-for="(level, index) in props.levels" :key="level.id">
            <rm-grid-descriptor
                v-if="props.indicator.descriptors[index] !== null"
                v-model:indicator-mark="indicatorMark"
                :descriptor="props.indicator.descriptors[index]"
                :self-assessment-indicator-mark="props.selfAssessmentIndicatorMark"
                :level="level"
                :participant-id="props.participantId"
            />
            <td v-else class="rb-blank hide-for-medium-down">&nbsp;</td>
        </template>
    </tr>
</template>
