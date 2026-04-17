<script setup lang="ts">
/**
 * The markable rubric grid or readonly version with feedback, depending on the context.
 *
 * Used in:
 * - the assessment inline grading page (for self assessment and instructor grading)
 *
 * And potentially used in:
 * - summative reports
 * - submission history (for a feedback interaction)
 * - submission history (for the marking guide)
 * - assessment create/modify page (when readonly)
 *
 * This creates a form element for each rubric with the name "rubric[${rubricId}]"
 * which contains a JSON-encoded version of the rubric grid for the backend to process
 */

import { cloneDeep } from "es-toolkit";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import type { MarkType } from "@schoolboxAssessment/Types/MarkType";
import FieldsetCollapsible from "@schoolboxVue/components/fieldset-collapsible.vue";
import type { GradeScheme } from "@schoolboxLibrary/Learning/Assessment/templates/fragments/vue/mark-type.vue";
import type { RubricGrid, ResultSet } from "./types";
import RfRubricGrid from "./rf-rubric-grid.vue";

interface Props {
    rubricGridsPayload: RubricGrid[];
    rubricBlankGrid: RubricGrid;
    resultSets: ResultSet[];
    capabilityMarkTypes: MarkType[];
    capabilityGradingSchemes: GradeScheme[];
    defaultGradeGroup: number;
    maxLevels: number;
    folderId: number;
    assessmentId: number | null;
}
const props = defineProps<Props>();

const rubricGrids = window.Vue.ref(cloneDeep(props.rubricGridsPayload)); // local mutable copy

/**
 * Calculates the assessment's total weight from the rubric grids
 */
const totalRubricWeight = window.Vue.computed(() => {
    let totalWeight = 0;
    for (const rubric of rubricGrids.value) {
        totalWeight += rubric.weight ?? 0; // rubrics without a weight are equivalent to 0 weight
    }

    return totalWeight;
});
</script>

<template>
    <fieldset-collapsible v-for="(rubric, index) in rubricGrids" :key="rubric.id" :initially-expanded="true">
        <template #legend>{{ rubric.title.length ? rubric.title : `Rubric ${index + 1}` }}</template>

        <template #content>
            <rf-rubric-grid
                v-model:rubric-grid-levels="rubric.levels"
                v-model:rubric-grid-capabilities="rubric.capabilities"
                :rubric="rubric"
                :result-sets="props.resultSets"
                :folder-id="props.folderId"
                :max-levels="props.maxLevels"
                :capability-mark-type="rubric.capabilityMarkType ?? MarkTypeSlug.NONE"
            />
        </template>
    </fieldset-collapsible>
</template>
