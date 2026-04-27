<script setup lang="ts">
/**
 * readonly rubric grid
 */

import locale from "@js/terminology/rosettaStone";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import RrGridCapability from "./rr-grid-capability.vue";
import type { RubricGrid, RubricMarkedGrid, RubricMarkedGridCapability } from "./types";

interface Props {
    rubric: RubricGrid;
    rubricMarkedGrid: RubricMarkedGrid | null;
    selfAssessmentFeedback: RubricMarkedGrid | null;
    assessmentMarkScheme: MarkScheme;
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
    /**
     * Whether points should be shown alongside descriptors,
     * note there is also a config setting that also controls this, false for self-reflections.
     * TODO: this probably needs to be delt with in SelfAssessmentFeedback as otherwise we'd be sending potentially values someone shouldn't normally access
     */
    showDescriptorValue?: boolean;
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
                <rr-grid-capability
                    v-for="capability in props.rubric.capabilities"
                    :key="capability.id"
                    :capability="capability"
                    :capability-mark="findCapabilityMark(capability.id, props.rubricMarkedGrid)"
                    :self-assessment-capability-mark="findCapabilityMark(capability.id, props.selfAssessmentFeedback)"
                    :levels="props.rubric.levels"
                    :participant-id="props.participantId"
                    :show-summary-capability="
                        props.showGroupTotal &&
                        !!props.rubricMarkedGrid &&
                        props.rubric.capabilityMarkType !== null &&
                        props.rubric.capabilityMarkType !== MarkTypeSlug.NONE
                    "
                    :show-descriptor-value="props.showDescriptorValue"
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
                        {{ props.rubricMarkedGrid.humanReadableMark }}

                        <!-- Self-Assessment -->
                        <template v-if="props.selfAssessmentFeedback?.humanReadableMark">
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
