<script setup lang="ts">
/**
 * The contents of a given descriptor cell (child of an indicator) in the readonly rubric grid
 */

import locale from "@js/terminology/rosettaStone";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type {
    RubricGridDescriptor,
    RubricGridLevel,
    RubricMarkedGridDescriptor,
    RubricMarkedGridIndicator,
} from "./types";

interface Props {
    descriptor: RubricGridDescriptor;
    indicatorMark: RubricMarkedGridIndicator | null;
    descriptorMark: RubricMarkedGridDescriptor | null;
    selfAssessmentDescriptorMark: RubricMarkedGridDescriptor | null;
    level: RubricGridLevel;
    participantId: UserAuthor["id"];
    showDescriptorValue?: boolean;
}
const props = defineProps<Props>();

const isSelfGrading = false; // TODO: selfAssessmentFeedback.isSelfGrading
</script>

<template>
    <td :class="{ 'rb-selected': descriptorMark?.selected }">
        <div>
            <div class="hide-for-large-up l-pad-m hide-on-print">
                <!-- display rubric levels for the mobile view. -->
                <p>
                    <!-- TODO: should be scrubbed (markdown) and output as html -->
                    <b>{{ level.name }}</b>
                </p>
            </div>

            <span class="rb-description markdown">
                <!-- Self-Assessment  -->
                <user-photo
                    v-if="props.selfAssessmentDescriptorMark?.selected && props.participantId !== null"
                    :user-id="props.participantId"
                    class="rb-self-reflection-choice"
                    :description="
                        isSelfGrading
                            ? `(${props.selfAssessmentDescriptorMark.value}) ${locale.selfAssessment.proper()} choice`
                            : `${locale.selfReflection.proper()} choice`
                    "
                />

                <!-- TODO: should be scrubbed (markdown) and output as html -->
                {{ props.descriptor.description.trim() }}
            </span>

            <span v-if="props.showDescriptorValue && props.descriptorMark && props.descriptorMark.value !== null">
                <span class="rb-value used">
                    <template v-if="'max' in props.descriptorMark.value">
                        {{ props.descriptorMark.value.max }}
                    </template>
                    <template v-else>
                        <!-- show indicator mark if available -->
                        <span v-if="props.descriptorMark.selected && props.indicatorMark" class="range-selection-value">
                            {{ props.indicatorMark.humanReadableMark }}
                        </span>

                        <!-- mark range -->
                        <span class="range" :class="{ selected: props.descriptorMark.selected }">
                            {{ props.descriptorMark.value.left }}–{{ props.descriptorMark.value.right }}
                        </span>
                    </template>
                </span>
            </span>
        </div>
    </td>
</template>
