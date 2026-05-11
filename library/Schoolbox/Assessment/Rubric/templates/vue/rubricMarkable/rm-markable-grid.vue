<script setup lang="ts">
/**
 * The markable rubric grid.
 * Used in the assessment inline grading page (for self assessment and instructor grading)
 *
 * This creates a form element for each rubric with the name "rubric[${rubricId}]"
 * which contains a JSON-encoded version of the rubric grid for the backend to process
 */

import locale from "@js/terminology/rosettaStone";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import type { User } from "@schoolboxLibrary/types/User";
import { applyMarkScheme, getPercentage } from "./rubricUtils";
import type { RubricGrid, RubricMarkedGrid } from "./types";
import RmGridCapability from "./rm-grid-capability.vue";

interface Props {
    rubric: RubricGrid;
    selfAssessmentFeedback: RubricMarkedGrid | null;
    assessmentMarkScheme: MarkScheme;
    participantId: User["id"] | null;
    showRubricTotal?: boolean;
    showGroupTotal?: boolean;
}

const props = defineProps<Props>();
const rubricMarkedGrid = defineModel<RubricMarkedGrid>("rubricMarkedGrid", { required: true });

/** Supress level headings if none have names */
const hasLevels = window.Vue.computed(() => props.rubric.levels.some((level) => level.name.trim().length > 0));

/** All capabilities, with their associated mark data */
const capabilities = window.Vue.computed(() => {
    return props.rubric.capabilities.map((capability) => {
        const capabilityMark = rubricMarkedGrid.value.capabilities.find((cap) => cap.id === capability.id) ?? null;
        const selfAssessmentCapabilityMark =
            props.selfAssessmentFeedback?.capabilities.find((cap) => cap.id === capability.id) ?? null;

        // if a capability exists in the rubric, it must always exist in the marked grid
        if (!capabilityMark) {
            throw new Error(`Unable to find capability mark for capability "${capability.id}"`);
        }

        return {
            capability: capability,
            mark: capabilityMark,
            selfAssessmentMark: selfAssessmentCapabilityMark,
        };
    });
});

/**
 * The mark of this rubric derived from the current scaled capability marks
 */
const derivedRubricMark = window.Vue.computed(() => {
    let total = 0;
    let hasMeaningfulMark = false;

    for (const capability of rubricMarkedGrid.value.capabilities) {
        if (capability.scaledMark !== null) {
            total += capability.scaledMark;
            hasMeaningfulMark = true;
        }
    }

    if (!hasMeaningfulMark) {
        return null;
    }

    // if the total is not a whole number, round to 2dp
    if (total % 1 !== 0) {
        total = parseFloat(total.toFixed(2));
    }

    return total;
});

/**
 * When a capability mark is updated, update the grid total
 */
window.Vue.watch(derivedRubricMark, (scaledMark) => {
    const { humanReadableMark } = applyMarkScheme(scaledMark, props.rubric.derivedMaxValue, props.assessmentMarkScheme);
    rubricMarkedGrid.value.humanReadableMark = humanReadableMark;
    rubricMarkedGrid.value.normalisedMark = getPercentage(scaledMark, props.rubric.derivedMaxValue);
});

/**
 * When the grid total changes, reflect that in the mark input & overall assign from button
 * TODO: this is currently outside of Vue, also should factor in multi rubrics
 */
window.Vue.watch(
    () => rubricMarkedGrid.value.humanReadableMark,
    () => {
        document.dispatchEvent(new CustomEvent("RUBRIC__TOTAL_MARK_UPDATED"));
    },
);
</script>

<template>
    <div class="scrollable">
        <table class="rb markable">
            <thead v-if="hasLevels">
                <tr>
                    <th colspan="2">&nbsp;</th>
                    <!-- TODO: level.name should be scrubbed (markdown) and output as html -->
                    <th v-for="level in props.rubric.levels" :key="level.id" class="markdown">{{ level.name }}</th>
                </tr>
            </thead>
            <tbody>
                <rm-grid-capability
                    v-for="capability in capabilities"
                    :key="capability.capability.id"
                    v-model:capability-mark="capability.mark"
                    :capability="capability.capability"
                    :self-assessment-capability-mark="capability.selfAssessmentMark"
                    :capability-mark-scheme="props.rubric.capabilityMarkScheme"
                    :levels="props.rubric.levels"
                    :participant-id="props.participantId"
                    :show-summary-capability="props.showGroupTotal"
                />

                <!-- Overall Rubric Mark -->
                <tr v-if="props.showRubricTotal && props.assessmentMarkScheme.context.mode !== MarkTypeSlug.NONE">
                    <th
                        class="rb-final-summary"
                        :colspan="props.rubric.levels.length + 2"
                        :data-rubric-sum-value="rubricMarkedGrid.humanReadableMark"
                        data-test="rubric-final-summary"
                    >
                        {{ rubricMarkedGrid.humanReadableMark }}

                        <!-- Self-Assessment -->
                        <template v-if="props.selfAssessmentFeedback?.humanReadableMark">
                            <!-- TODO: instructor mark type is used here... thanks past me -->
                            <!--
                            Although the student entered the mark using the display mark type,
                            we show it here using the input mark type so that it matches what the instructor
                            is currently entering so it can be easily compared.
                            -->
                            ({{ props.selfAssessmentFeedback.humanReadableMark }}
                            given in
                            {{ locale.selfAssessment.singular() }})
                        </template>
                    </th>
                </tr>
            </tbody>
        </table>
    </div>
</template>
