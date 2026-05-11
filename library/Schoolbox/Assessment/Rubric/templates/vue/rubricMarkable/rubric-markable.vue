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
import type { RubricGrid, RubricMarkedGrid, RubricMarkedGridCapability, RubricMarkedGridIndicator } from "./types";
import RmMarkableGrid from "./rm-markable-grid.vue";
import { applyMarkScheme } from "./rubricUtils";

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
            gridId: payload.rubric.id,
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

/**
 * Calculates the assessment's total weight from the rubric grids
 */
const totalRubricWeight = window.Vue.computed(() => {
    let totalWeight = 0;
    for (const { rubric } of rubrics.value) {
        totalWeight += rubric.weight ?? 0; // rubrics without a weight are equivalent to 0 weight
    }

    return totalWeight;
});

/**
 * The assessment's max available marks from the rubric grid(s)
 * based on the max value for each rubric and factoring in rubric weights if they exist
 */
const derivedMaxMark = window.Vue.computed(() => {
    let total = 0;

    for (const { rubric } of rubrics.value) {
        let weightMultiplier = 0;
        if (totalRubricWeight.value > 0) {
            // rubrics without a weight are equivalent to 0 weight
            weightMultiplier = (rubric.weight ?? 0) / totalRubricWeight.value;
        }

        total += rubric.derivedMaxValue * weightMultiplier;
    }

    // if the total is not a whole number, round to 2dp
    if (total % 1 !== 0) {
        total = parseFloat(total.toFixed(2));
    }

    return total;
});

/**
 * The combined total mark across all rubric grids, derived from the individual grid totals and rubric weights.
 * Generally this also represents the final mark for the assessment, assuming it hasn't been customised separately.
 */
const derivedTotalMark = window.Vue.computed(() => {
    let totalNormalisedMark = 0;
    let hasMeaningfulMark = false;

    for (const { rubric, markedGrid } of rubrics.value) {
        if (markedGrid.normalisedMark === null) {
            continue;
        }

        hasMeaningfulMark = true;

        let weightMultiplier = 0;
        if (totalRubricWeight.value > 0) {
            // rubrics without a weight are equivalent to 0 weight
            weightMultiplier = (rubric.weight ?? 0) / totalRubricWeight.value;
        }

        totalNormalisedMark += markedGrid.normalisedMark * weightMultiplier;
    }

    if (!hasMeaningfulMark) {
        return null;
    }

    // figure out the 'scaled mark' so we can apply the mark scheme to it
    let scaledMark = (totalNormalisedMark / 100) * derivedMaxMark.value;

    // if the normalised or scaled mark is not a whole number, round to 2dp
    if (scaledMark % 1 !== 0) {
        scaledMark = parseFloat(scaledMark.toFixed(2));
    }

    if (totalNormalisedMark % 1 !== 0) {
        totalNormalisedMark = parseFloat(totalNormalisedMark.toFixed(2));
    }

    const { humanReadableMark } = applyMarkScheme(scaledMark, derivedMaxMark.value, props.assessmentMarkScheme);

    return {
        normalisedMark: totalNormalisedMark,
        humanReadableMark,
    };
});

/**
 * Calculate a scaled rubric weight and round to 2dp if necessary for display
 * @param rubric the rubric to calculate the scaled weight for
 * @returns scaled weight, or 0 if it doesn't have a weight
 */
function getScaledRubricWeight(rubric: RubricGrid): number {
    if (totalRubricWeight.value <= 0) {
        return 0;
    }

    const weight = ((rubric.weight ?? 0) / totalRubricWeight.value) * 100;

    if (weight % 1 !== 0) {
        return parseFloat(weight.toFixed(2));
    }

    return weight;
}

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
        legend-class="l-pad-m--horiz"
    >
        <template #legend>
            {{ rubric.rubric.title.length ? rubric.rubric.title : `Rubric ${index + 1}` }}
            <!-- Append the weighting, if it matters -->
            <template v-if="rubrics.length > 1 && totalRubricWeight > 0">
                -
                {{ getScaledRubricWeight(rubric.rubric) }}% Weighting
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
</template>
