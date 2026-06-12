import type { Ref } from "vue";
import { applyMarkScheme } from "../rubricUtils";
import type { RubricGrid, RubricMarkedGrid } from "../types";
import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";

/**
 * To calculate aggregated mark info for several rubrics
 * @param assessmentMarkScheme mark scheme the assessment uses (aka, the mark scheme that the aggregated result should be in)
 * @param rubrics the individual rubrics and their marked grids to aggregate the marks from
 * @returns calculation info
 */
// eslint-disable-next-line @typescript-eslint/explicit-function-return-type -- inferred complex types from composables/stores are okay
export function useRubricMarks(
    assessmentMarkScheme: MarkScheme,
    rubrics: Ref<{ rubric: RubricGrid; markedGrid: RubricMarkedGrid }[]>,
) {
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
        // When there are no marks to give, we shouldn't start setting the final grade to "0 %"
        if (derivedMaxMark.value === 0) {
            return null;
        }

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

        const { humanReadableMark } = applyMarkScheme(scaledMark, derivedMaxMark.value, assessmentMarkScheme);

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

    return {
        totalRubricWeight,
        derivedMaxMark,
        derivedTotalMark,
        getScaledRubricWeight,
    };
}
