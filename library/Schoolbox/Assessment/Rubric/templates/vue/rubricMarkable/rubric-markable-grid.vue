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
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import RmGridCapability from "./rm-grid-capability.vue";
import type { RubricGrid, RubricMarkedGrid, RubricMarkedGridCapability } from "./types";

interface Props {
    rubric: RubricGrid;
    rubricMarkedGrid: RubricMarkedGrid | null;
    selfAssessmentFeedback: RubricMarkedGrid | null;
    assessmentMarkScheme: MarkScheme;
    /**
     * The ID of the participant being assessed, or null if blind marking
     */
    participantId: UserAuthor["id"] | null;
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

/** Supress level headings if none have names */
const hasLevels = window.Vue.computed(() => props.rubric.levels.some((level) => level.name.trim().length > 0));

/**
 * Find a capability mark for a specific capability in a RubricMarkedGrid
 * @param capabilityId the id of the capability to find the mark for
 * @param markedGrid the RubricMarkedGrid to find the mark in
 * @returns the capability mark, or null if not found
 */
function findCapabilityMark(
    capabilityId: number,
    markedGrid: RubricMarkedGrid | null,
): RubricMarkedGridCapability | null {
    if (!markedGrid) {
        return null;
    }
    return markedGrid.capabilities.find((cap) => cap.id === capabilityId) ?? null;
}
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
                    v-for="capability in props.rubric.capabilities"
                    :key="capability.id"
                    :capability="capability"
                    :capability-mark="findCapabilityMark(capability.id, props.rubricMarkedGrid)"
                    :self-assessment-capability-mark="findCapabilityMark(capability.id, props.selfAssessmentFeedback)"
                    :capability-mark-scheme="props.rubric.capabilityMarkScheme"
                    :levels="props.rubric.levels"
                    :participant-id="props.participantId"
                    :show-summary-capability="props.showGroupTotal && !!props.rubricMarkedGrid"
                />

                <!-- Overall Mark -->
                <tr
                    v-if="
                        props.showRubricTotal &&
                        props.rubricMarkedGrid &&
                        props.assessmentMarkScheme.context.mode !== MarkTypeSlug.NONE
                    "
                >
                    <th class="rb-final-summary" :colspan="props.rubric.levels.length + 2">
                        <!-- TODO data-final-summary="{{ rubricId }}" -->
                        <!-- TODO data-scaled-mark="{{ rubricScaledMark }}" -->
                        <!-- TODO data-max="{{ rubricMax }}" -->
                        <!-- TODO data-mark-type="{{ markType.mode }}" -->
                        {{ props.rubricMarkedGrid.humanReadableMark }}

                        <!-- Self-Assessment -->
                        <template v-if="props.selfAssessmentFeedback?.humanReadableMark">
                            <!-- TODO: instructor mark type is used here... thanks past me -->
                            <!--
                            Although the student entered the mark using the display mark type,
                            we show it here using the input mark type so that it matches what the instructor
                            is currently entering so it can be easily compared.
                            -->
                            {{ props.selfAssessmentFeedback.humanReadableMark }}
                            given in
                            {{ locale.selfAssessment.singular() }}
                        </template>
                    </th>
                </tr>
            </tbody>
        </table>
    </div>
</template>
