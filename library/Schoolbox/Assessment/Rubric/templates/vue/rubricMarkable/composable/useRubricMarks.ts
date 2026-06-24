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
     * Calculate the derived mark for each rubric grid after applying the relevant weight
     */
    const derivedRubricMarks = window.Vue.computed(() => {
        return rubrics.value.map(({ rubric, markedGrid }) => {
            let scaledWeight = 0;
            if (totalRubricWeight.value > 0) {
                // rubrics without a weight are equivalent to 0 weight
                scaledWeight = (rubric.weight ?? 0) / totalRubricWeight.value;
            }

            let scaledWeightRounded = scaledWeight * 100;
            if (scaledWeightRounded % 1 !== 0) {
                scaledWeightRounded = parseFloat(scaledWeightRounded.toFixed(2));
            }

            const baseInfo = {
                rubricId: rubric.id,
                scaledWeight,
                scaledWeightRounded,
            };

            if (markedGrid.normalisedMark === null) {
                return {
                    ...baseInfo,
                    weightedMark: null,
                    weightedMarkRounded: null,
                };
            }

            const weightedMark = markedGrid.normalisedMark * scaledWeight;

            let weightedMarkRounded = weightedMark;
            if (weightedMarkRounded % 1 !== 0) {
                weightedMarkRounded = parseFloat(weightedMarkRounded.toFixed(2));
            }

            return {
                ...baseInfo,
                weightedMark,
                weightedMarkRounded,
            };
        });
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

        const meaningfulWeightedMarks = derivedRubricMarks.value.filter((m) => m.weightedMark !== null);
        if (meaningfulWeightedMarks.length === 0) {
            return null;
        }

        let totalNormalisedMark = 0;
        for (const { weightedMark } of meaningfulWeightedMarks) {
            totalNormalisedMark += weightedMark ?? 0;
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

    return {
        totalRubricWeight,
        derivedMaxMark,
        derivedRubricMarks,
        derivedTotalMark,
    };
}
