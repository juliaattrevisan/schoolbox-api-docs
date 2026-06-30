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
import { applyMarkScheme, deriveRubricMark, getPercentage } from "./rubricUtils";
import type { RubricGrid, RubricMarkedGrid } from "./types";
import RmGridCapability from "./rm-grid-capability.vue";
import { translateMarkedGridToFormPayload } from "./payload";

interface Props {
    rubric: RubricGrid;
    selfAssessmentFeedback: RubricMarkedGrid | null;
    assessmentMarkScheme: MarkScheme;
    participantId: User["id"] | null;
    showRubricTotal?: boolean;
    showGroupTotal?: boolean;
    showDescriptorValue: boolean;
    isSelfGrading: boolean;
    isSelfAssess: boolean;
    isStudentView: boolean;
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
const derivedRubricMark = window.Vue.computed(() => deriveRubricMark(rubricMarkedGrid.value));

/**
 * When a capability mark is updated, update the grid total
 */
window.Vue.watch(derivedRubricMark, (scaledMark) => {
    const { humanReadableMark } = applyMarkScheme(scaledMark, props.rubric.derivedMaxValue, props.assessmentMarkScheme);
    rubricMarkedGrid.value.humanReadableMark = humanReadableMark;
    rubricMarkedGrid.value.normalisedMark = getPercentage(scaledMark, props.rubric.derivedMaxValue);
});

/**
 * The self-assessment's mark, re-expressed in the instructor's (input) mark scheme rather than the
 * display scheme the student saw, so it can be compared against the mark the instructor is entering.
 */
const selfAssessmentMark = window.Vue.computed<string | null>(() => {
    if (!props.selfAssessmentFeedback) {
        return null;
    }

    /* If there is no humanReadableMark, then the display mark type would've been NONE (or there's no mark at all),
     * it's still somehow possible to end up with a normalised mark in this case, but historically we haven't
     * shown a computed mark when humanReadableInstructorMark was null (theoretically should be the same as when
     * humanReadableMark is null in this specific scenario due to it being a self assessment and the usual order reversed).
     *
     * Anyway, to align with the old way we'll just hide it if there's no humanReadableMark, even if there's a norm mark.
     */
    if (props.selfAssessmentFeedback.humanReadableMark === null) {
        return null;
    }

    if (props.selfAssessmentFeedback.normalisedMark === null) {
        /* having no normalised mark whilst still having a humanReadableMark is strange, perhaps due to submission states?
         * Just show whatever humanReadableMark is in this case, we don't want to reverse the display mark type as it'll
         * loose precision and likely be inaccurate.
         */
        return props.selfAssessmentFeedback.humanReadableMark;
    }

    // need to rederive the scaled mark based on the normalisedMark for applyMarkScheme to receive the right value
    let scaledMark = (props.selfAssessmentFeedback.normalisedMark / 100) * props.rubric.derivedMaxValue;
    if (scaledMark % 1 !== 0) {
        scaledMark = parseFloat(scaledMark.toFixed(2));
    }

    return applyMarkScheme(scaledMark, props.rubric.derivedMaxValue, props.assessmentMarkScheme).humanReadableMark;
});

const formValue = window.Vue.computed(() => JSON.stringify(translateMarkedGridToFormPayload(rubricMarkedGrid.value)));
</script>

<template>
    <!-- hidden input to submit the rubric grid as JSON -->
    <input type="hidden" :name="`rubric[${rubric.id}]`" :value="formValue" />

    <div class="scrollable">
        <table class="rb markable">
            <thead v-if="hasLevels">
                <tr>
                    <th colspan="2">&nbsp;</th>
                    <!-- eslint-disable-next-line vue/no-v-html -- scrubbed in the backend -->
                    <th v-for="level in props.rubric.levels" :key="level.id" class="markdown" v-html="level.name"></th>
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
                    :show-descriptor-value="props.showDescriptorValue"
                    :is-self-grading="props.isSelfGrading"
                    :is-self-assess="props.isSelfAssess"
                    :is-student-view="props.isStudentView"
                />

                <!-- Overall Rubric Mark -->
                <tr v-if="props.showRubricTotal && props.assessmentMarkScheme.mode !== MarkTypeSlug.NONE">
                    <th
                        class="rb-final-summary"
                        :colspan="props.rubric.levels.length + 2"
                        :data-rubric-sum-value="rubricMarkedGrid.humanReadableMark"
                        data-test="rubric-final-summary"
                    >
                        {{ rubricMarkedGrid.humanReadableMark }}

                        <!-- Self-Assessment -->
                        <template v-if="selfAssessmentMark">
                            <!--
                            Although the student entered the mark using the display mark type,
                            we show it here using the input mark type so that it matches what the instructor
                            is currently entering so it can be easily compared.
                            -->
                            ({{ selfAssessmentMark }} given in {{ locale.selfAssessment.singular() }})
                        </template>
                    </th>
                </tr>
            </tbody>
        </table>
    </div>
</template>
