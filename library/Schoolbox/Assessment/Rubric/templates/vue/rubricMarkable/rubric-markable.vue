<script setup lang="ts">
/**
 * Wrapper for multiple markable rubric grids.
 * Used in the assessment inline grading page (for self assessment and instructor grading)
 *
 * This creates a form element for each rubric with the name "rubric[${rubricId}]"
 * which contains a JSON-encoded version of the rubric grid for the backend to process
 */

import { cloneDeep } from "es-toolkit";
import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import type { User } from "@schoolboxLibrary/types/User";
import FieldsetCollapsible from "@schoolboxVue/components/fieldset-collapsible.vue";
import { useRubricMarks } from "./composable/useRubricMarks";
import RubricCalculationDetailsModal from "./rubric-calculation-details-modal.vue";
import type { RubricGrid, RubricMarkedGrid, RubricMarkedGridCapability, RubricMarkedGridIndicator } from "./types";
import RmMarkableGrid from "./rm-markable-grid.vue";

interface Props {
    rubricMarkedGridsPayload: {
        rubric: RubricGrid;
        markedGrid: RubricMarkedGrid | null;
        selfAssessmentFeedback: RubricMarkedGrid | null;
    }[];
    assessmentMarkScheme: MarkScheme;
    /**
     * The ID of the participant being assessed, or null if blind marking
     */
    participantId: User["id"] | null;
    /**
     * Whether to show the totals at the bottom of overall rubric
     * This is false for student views, but true for staff views
     * TODO: this probably needs to be delt with in SelfAssessmentFeedback as otherwise we'd be sending potentially values someone shouldn't normally access
     */
    showRubricTotal?: boolean;
    /**
     * Whether to show group totals at the bottom of each capability,
     * can be inferred from (assessment.markPerCapability)
     * TODO: this probably needs to be delt with in SelfAssessmentFeedback as otherwise we'd be sending potentially values someone shouldn't normally access
     */
    showGroupTotal?: boolean;
    /**
     * TODO: likely a sign of permission issues, but for now pass these thru.
     */
    showDescriptorValue?: boolean;
    /**
     * From "selfAssessmentFeedback.isSelfGrading"
     * TODO: likely a sign of permission issues, but for now pass these thru.
     */
    isSelfGrading?: boolean;
    /**
     * From "selfAssess"
     * TODO: likely a sign of permission issues, but for now pass these thru.
     */
    isSelfAssess?: boolean;
    /**
     * From "studentView"
     * TODO: likely a sign of permission issues, but for now pass these thru.
     */
    isStudentView?: boolean;
}
const props = defineProps<Props>();

type RubricData = {
    rubric: RubricGrid;
    markedGrid: RubricMarkedGrid;
    selfAssessmentFeedback: RubricMarkedGrid | null;
};

/**
 * local mutable copy of props.rubricMarkedGridsPayload,
 * also ensuring that each has at least a blank marked grid if one isn't provided to simplify logic
 */
const rubrics = window.Vue.ref<RubricData[]>(
    props.rubricMarkedGridsPayload.map((payload) => {
        /** A blank marked grid which will be used if one isn't provided */
        const blankMarkedGrid: RubricMarkedGrid = {
            rubricId: payload.rubric.id,
            capabilities: payload.rubric.capabilities.map((capability): RubricMarkedGridCapability => {
                return {
                    id: capability.id,
                    humanReadableMark: null,
                    value: null,
                    scaledMark: null,
                    indicators: capability.indicators.map((indicator): RubricMarkedGridIndicator => {
                        return {
                            id: indicator.id,
                            descriptorId: null,
                            value: null,
                        };
                    }),
                };
            }),
            humanReadableMark: null,
            normalisedMark: null,
        };

        // Although the entire thing is cloned, only markedGrid is expected to be mutated at the moment (It won't break if you mutate others)
        return cloneDeep({
            rubric: payload.rubric,
            markedGrid: payload.markedGrid ?? blankMarkedGrid,
            selfAssessmentFeedback: payload.selfAssessmentFeedback,
        });
    }),
);

const { derivedRubricMarks, derivedTotalMark, totalRubricWeight } = useRubricMarks(props.assessmentMarkScheme, rubrics);

/**
 * When the combined total mark changes, reflect that in the mark input & overall assign from button
 * TODO: this is currently outside of Vue, also should factor in multi rubrics
 */
window.Vue.watch(
    () => derivedTotalMark.value?.humanReadableMark,
    async () => {
        await window.Vue.nextTick(); // need the form value ot update first
        document.dispatchEvent(new CustomEvent("RUBRIC__TOTAL_MARK_UPDATED"));
    },
);
</script>

<template>
    <fieldset-collapsible
        v-for="(rubric, index) in rubrics"
        :key="rubric.rubric.id"
        initially-expanded
        legend-class="u-pad-m--horiz"
    >
        <template #legend>
            {{ rubric.rubric.title.length ? rubric.rubric.title : `Rubric ${index + 1}` }}
            <!-- Append the weighting, if it matters -->
            <template v-if="rubrics.length > 1 && totalRubricWeight > 0">
                -
                {{ derivedRubricMarks.find((m) => m.rubricId === rubric.rubric.id)?.scaledWeightRounded }}% Weighting
            </template>
        </template>

        <template #content>
            <rm-markable-grid
                v-model:rubric-marked-grid="rubric.markedGrid"
                :rubric="rubric.rubric"
                :self-assessment-feedback="rubric.selfAssessmentFeedback"
                :assessment-mark-scheme="props.assessmentMarkScheme"
                :participant-id="props.participantId"
                :show-rubric-total="props.showRubricTotal"
                :show-group-total="props.showGroupTotal"
                :show-descriptor-value="props.showDescriptorValue"
                :is-self-grading="props.isSelfGrading"
                :is-self-assess="props.isSelfAssess"
                :is-student-view="props.isStudentView"
            />
        </template>
    </fieldset-collapsible>

    <!-- Overall Combined Total Mark - made accessible to the "Assign ... from Rubric" button -->
    <input
        id="rubric-total-mark"
        type="hidden"
        name="rubric-total-mark"
        :value="derivedTotalMark?.humanReadableMark ?? ''"
    />

    <rubric-calculation-details-modal :rubrics="rubrics" />
</template>
