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
    participantId: UserAuthor["id"] | null;
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

const selfAssessmentTooltip = window.Vue.computed(() => {
    if (
        isSelfGrading &&
        props.selfAssessmentIndicatorMark &&
        props.selfAssessmentIndicatorMark.descriptorId === props.descriptor.id
    ) {
        return `(${props.selfAssessmentIndicatorMark.humanReadableMark}) ${locale.selfAssessment.proper()} choice`;
    }

    return `${locale.selfReflection.proper()} choice`;
});

const hasMarkRange = window.Vue.computed(() => props.descriptor.value !== null && "left" in props.descriptor.value);
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
                <template
                    v-if="
                        props.selfAssessmentIndicatorMark &&
                        props.selfAssessmentIndicatorMark.descriptorId === props.descriptor.id
                    "
                >
                    <p
                        v-if="props.participantId === null"
                        class="rb-self-reflection-choice blind-mode"
                        :title="selfAssessmentTooltip"
                    >
                        <!-- blind mode-->
                    </p>
                    <user-photo
                        v-else
                        :user-id="props.participantId"
                        class="rb-self-reflection-choice"
                        render="tiny"
                        :description="selfAssessmentTooltip"
                    />
                </template>

                <!-- TODO: should be scrubbed (markdown) and output as html -->
                {{ props.descriptor.description?.trim() }}
            </span>

            <!-- Just show a text value if the descriptor is not selected -->
            <!-- TODO: hide for self review (the only condition where showDescriptorValue is false) -->
            <span
                v-if="props.descriptor.value !== null && (!hasMarkRange || !isDescriptorSelected)"
                class="rb-value"
                :class="{ used: !hasMarkRange }"
            >
                <template v-if="'left' in props.descriptor.value">
                    <!-- mark range -->
                    {{ props.descriptor.value.left }}–{{ props.descriptor.value.right }}
                </template>
                <template v-else>
                    {{ props.descriptor.value.max }}
                </template>
            </span>
        </label>
    </td>
</template>
