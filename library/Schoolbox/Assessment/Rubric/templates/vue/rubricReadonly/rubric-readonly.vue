<script setup lang="ts">
/**
 * Wrapper for multiple readonly rubric grids.
 */

import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import type { User } from "@schoolboxLibrary/types/User";
import FieldsetCollapsible from "@schoolboxVue/components/fieldset-collapsible.vue";
import type { RubricGrid, RubricMarkedGrid } from "../rubricMarkable/types";
import RrReadonlyGrid from "./rr-readonly-grid.vue";

interface Props {
    rubricMarkedGridsPayload: {
        rubric: RubricGrid;
        markedGrid: RubricMarkedGrid | null;
        selfAssessmentFeedback: RubricMarkedGrid | null;
    }[];
    assessmentMarkScheme: MarkScheme;
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
</script>

<template>
    <fieldset-collapsible
        v-for="(rubric, index) in props.rubricMarkedGridsPayload"
        :key="rubric.rubric.id"
        initially-expanded
        legend-class="l-pad-m--horiz"
    >
        <template #legend>{{ rubric.rubric.title.length ? rubric.rubric.title : `Rubric ${index + 1}` }}</template>

        <template #content>
            <rr-readonly-grid
                :rubric="rubric.rubric"
                :rubric-marked-grid="rubric.markedGrid"
                :self-assessment-feedback="rubric.selfAssessmentFeedback"
                :assessment-mark-scheme="props.assessmentMarkScheme"
                :participant-id="props.participantId"
                :show-rubric-total="props.showRubricTotal"
                :show-group-total="props.showGroupTotal"
            />
        </template>
    </fieldset-collapsible>
</template>
