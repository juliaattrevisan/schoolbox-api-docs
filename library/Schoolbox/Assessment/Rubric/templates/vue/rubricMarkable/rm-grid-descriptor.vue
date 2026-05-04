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

const isSelfGrading = false; // TODO: from 'selfAssessmentFeedback.isSelfGrading'
const isSelfReflect = false; // TODO: from 'showDescriptorValue'
const isSelfAssess = false; // TODO: from 'selfAssess'
const isStudentView = false; // TODO: from 'studentView'

const selfAssessmentTooltip = window.Vue.computed(() => {
    if (
        isSelfGrading &&
        props.selfAssessmentIndicatorMark &&
        props.selfAssessmentIndicatorMark.descriptorId === props.descriptor.id
    ) {
        return `(${props.selfAssessmentIndicatorMark.value}) ${locale.selfAssessment.proper()} choice`;
    }

    return `${locale.selfReflection.proper()} choice`;
});

const markRangeValues = window.Vue.computed<number[]>(() => {
    if (props.descriptor.value === null) {
        return [];
    }

    if ("max" in props.descriptor.value) {
        return [props.descriptor.value.max];
    }

    let { left, right } = props.descriptor.value;
    const inverted = left > right;
    if (inverted) {
        [left, right] = [right, left];
    }

    const range = [];
    for (let i = left; i <= right; i++) {
        range.push(i);
    }
    return inverted ? range.reverse() : range;
});

const hasMarkRange = window.Vue.computed(() => props.descriptor.value !== null && "left" in props.descriptor.value);

/**
 * Internal cache of the selected value for this descriptor
 * default to the max value (unless it can be derived from the indicator mark, see onMounted)
 */
const descriptorValue = window.Vue.ref<number>(
    props.descriptor.value !== null
        ? "max" in props.descriptor.value
            ? props.descriptor.value.max
            : Math.max(props.descriptor.value.left, props.descriptor.value.right)
        : 0,
);

const isDescriptorSelected = window.Vue.computed({
    get: () => indicatorMark.value.descriptorId === props.descriptor.id,
    set: (value: boolean) => {
        if (!value) {
            // deselected; so clear the descriptorId and value

            // shouldn't have a race condition as the setter only runs
            // when this specific component was responsible for the change
            indicatorMark.value.descriptorId = null;
            indicatorMark.value.value = null;
            return;
        }

        // selected; update the indicator mark
        indicatorMark.value.descriptorId = props.descriptor.id;
        indicatorMark.value.value = descriptorValue.value;
    },
});

window.Vue.onMounted(() => {
    /* if initially selected, then the indicator mark is responsible for the value */
    if (isDescriptorSelected.value && indicatorMark.value.value !== null) {
        descriptorValue.value = indicatorMark.value.value;
    }

    /* and reflect further updates to descriptorValue (from the range dropdown) back to the indicator mark */
    window.Vue.watch(descriptorValue, (newValue) => {
        if (isDescriptorSelected.value) {
            indicatorMark.value.value = newValue;
        }
    });
});
</script>

<template>
    <td :class="{ 'rb-selected': isDescriptorSelected }">
        <div class="rb-descriptor-cell">
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
                <span
                    v-if="props.descriptor.value !== null && (!hasMarkRange || !isDescriptorSelected) && !isSelfReflect"
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

            <!-- Render a dropdown to select a value from the range -->
            <!-- TODO: this select needs a label; technically it should be the one above but that's already in use for the descriptor checkbox -->
            <!-- TODO: data-rubric="{{ rubricGrid.id }}" -->
            <!-- TODO: data-rubric-group="{{ groupUsage.capability.id }}" -->
            <!-- TODO: data-rubric-descriptor="{{ descriptor.id }}" -->
            <!-- TODO: data-rubric-indicator="{{ indicatorId }}" -->
            <select
                v-if="isDescriptorSelected && hasMarkRange && (!isStudentView || isSelfAssess)"
                :id="`descriptor-${props.descriptor.id}-rangeDropdown-${componentId}`"
                v-model="descriptorValue"
                :class="{ used: hasMarkRange }"
            >
                <option v-for="option in markRangeValues" :key="option" :value="option">{{ option }}</option>
            </select>
        </div>
    </td>
</template>
