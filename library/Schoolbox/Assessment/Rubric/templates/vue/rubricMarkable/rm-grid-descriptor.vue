<script setup lang="ts">
/**
 * The contents of a given descriptor cell (child of an indicator) in the markable rubric grid
 */

import locale from "@js/terminology/rosettaStone";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { RubricGridDescriptor, RubricGridLevel, RubricMarkedGridIndicator } from "./types";

interface Props {
    descriptor: RubricGridDescriptor;
    selfAssessmentIndicatorMark: RubricMarkedGridIndicator | null;
    level: RubricGridLevel;
    participantId: UserAuthor["id"];
}
const props = defineProps<Props>();
const indicatorMark = defineModel<RubricMarkedGridIndicator>("indicatorMark", { required: true });

const componentId = window.Vue.useId();

const isSelfGrading = false; // TODO: selfAssessmentFeedback.isSelfGrading

const isDescriptorSelected = window.Vue.computed({
    get: () => indicatorMark.value.descriptorId === props.descriptor.id,
    set: (value: boolean) => {
        indicatorMark.value.descriptorId = value ? props.descriptor.id : null;
    },
});
</script>

<template>
    <td :class="{ 'rb-selected': isDescriptorSelected }">
        <!-- TODO: data-value="{{ descriptor.maxValue }}" -->
        <!-- TODO: data-rubric="{{ rubricGrid.assessment.id }}" -->
        <!-- TODO: data-rubric-group="{{ groupUsage.capability.id }}" -->
        <!-- TODO: data-rubric-descriptor="{{ descriptor.id }}" -->
        <!-- TODO: data-rubric-indicator="{{ indicatorId }}" -->
        <input
            :id="`descriptor-${props.descriptor.id}-${componentId}`"
            v-model="isDescriptorSelected"
            type="checkbox"
        />

        <label :for="`descriptor-${props.descriptor.id}-${componentId}`">
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
                    v-if="
                        props.selfAssessmentIndicatorMark &&
                        props.selfAssessmentIndicatorMark.descriptorId === props.descriptor.id &&
                        props.participantId !== null
                    "
                    :user-id="props.participantId"
                    class="rb-self-reflection-choice"
                    render="tiny"
                    :description="
                        isSelfGrading
                            ? `(${props.selfAssessmentIndicatorMark.humanReadableMark}) ${locale.selfAssessment.proper()} choice`
                            : `${locale.selfReflection.proper()} choice`
                    "
                />

                <!-- TODO: should be scrubbed (markdown) and output as html -->
                {{ props.descriptor.description?.trim() }}
            </span>

            <span v-if="props.descriptor.value !== null" class="rb-value used">
                <template v-if="'left' in props.descriptor.value">
                    <!-- show indicator mark if available -->
                    <span
                        v-if="isDescriptorSelected && indicatorMark && indicatorMark.humanReadableMark !== null"
                        class="range-selection-value"
                    >
                        {{ indicatorMark.humanReadableMark }}
                    </span>

                    <!-- mark range -->
                    <span class="range" :class="{ selected: isDescriptorSelected }">
                        {{ props.descriptor.value.left }}–{{ props.descriptor.value.right }}
                    </span>
                </template>
                <template v-else>
                    {{ props.descriptor.value.max }}
                </template>
            </span>
        </label>
    </td>
</template>
