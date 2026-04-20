<script setup lang="ts">
/**
 * readonly rubric grid
 */

import locale from "@js/terminology/rosettaStone";
import { shiftArrayItem } from "@js/util/arrayUtils";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import RfGridCapability from "./rf-grid-capability.vue";
import { translateGridToFormPayload } from "./payload";
import { MAX_CAPABILITY_LENGTH } from "./types";
import type { RubricGrid, ResultSet } from "./types";

type RubricMarkedGrid = {
    // TODO
    humanReadableMark: string | null;
};

type SelfAssessmentFeedback = {
    /** whether this feedback was created via the learner self-grade workflow */
    // isSelfGrading: boolean; // probably unnecessary if the existence of humanReadableMark is enough
    /**
     * the overall human-readable mark given
     * null if it shouldn't be displayed (e.g. if not feedback.isSelfGrading)
     */
    humanReadableMark: string | null; // TODO: markable grid uses instructor mark type instead
};

interface Props {
    rubric: RubricGrid;
    rubricMarkedGrid: RubricMarkedGrid | null;
    selfAssessmentFeedback: SelfAssessmentFeedback | null;
    assessmentMarkScheme: MarkScheme;
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

// const rubricGridLevels = defineModel<RubricGrid["levels"]>("rubricGridLevels", { required: true });
// const rubricGridCapabilities = defineModel<RubricGrid["capabilities"]>("rubricGridCapabilities", { required: true });

const formValueRef = window.Vue.useTemplateRef<HTMLInputElement>("formValueRef");

/** Supress level headings if none have names */
const hasLevels = window.Vue.computed(() => props.rubric.levels.some((level) => level.name.trim().length > 0));

/** Emit a change event so jquery.alaressValidate can pick it up for grid-based rules */
const formValue = window.Vue.computed(() => JSON.stringify(translateGridToFormPayload(props.rubric)));
window.Vue.watch(formValue, () => {
    if (formValueRef.value) {
        window.jQuery(formValueRef.value).valid();
    }
});
</script>

<template>
    <div class="scrollable">
        <table class="rb-markable">
            <thead v-if="hasLevels">
                <tr>
                    <th colspan="2">&nbsp;</th>
                    <!-- TODO: level.name should be scrubbed (markdown) and output as html -->
                    <th v-for="level in props.rubric.levels" :key="level.id" class="markdown">{{ level.name }}</th>
                </tr>
            </thead>
            <tbody>
                <!-- <rf-grid-capability
                    v-for="(capability, index) in rubricGridCapabilities"
                    :key="capability.id"
                    v-model:name="capability.name"
                    v-model:indicators="capability.indicators"
                    v-model:max-value="capability.maxValue"
                    :capability="capability"
                    :levels="rubricGridLevels"
                    :total-columns="totalColumns"
                    :is-creating="isCreating"
                    :max-levels="props.maxLevels"
                    :capability-mark-type="props.capabilityMarkType"
                    :use-mark-range="props.rubric.useMarkRange"
                    :can-move-prev="index > 0"
                    :can-move-next="index < rubricGridCapabilities.length - 1"
                    @move="(dir) => shiftArrayItem(rubricGridCapabilities, index, dir)"
                    @add-indicator-keydown="(e, nameRef) => onAddKeydown(e, 'indicator', capability, nameRef)"
                /> -->

                <!-- {% include '@rubric/group-row-markable.twig' %} -->
            </tbody>

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
        </table>
    </div>
</template>
