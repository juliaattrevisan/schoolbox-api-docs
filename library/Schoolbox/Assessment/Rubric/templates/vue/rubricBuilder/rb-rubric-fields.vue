<script setup lang="ts">
/**
 * Editable fields shown above the rubric grid
 */

import locale from "@js/terminology/rosettaStone";
import MarkTypeInput from "@schoolboxLibrary/Learning/Assessment/templates/fragments/vue/mark-type.vue";
import type { GradeScheme } from "@schoolboxLibrary/Learning/Assessment/templates/fragments/vue/mark-type.vue";
import type { MarkType, MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import type { RubricGrid } from "./types";

interface Props {
    rubricId: RubricGrid["id"];
    capabilityMarkTypes: MarkType[];
    capabilityGradingSchemes: GradeScheme[];
    defaultGradeGroup: number;
}
const props = defineProps<Props>();

const rubricTitle = defineModel<RubricGrid["title"]>("rubricTitle", { required: true });
const rubricWeightModel = defineModel<RubricGrid["weight"]>("rubricWeight", { required: true });
const rubricUseMarkRange = defineModel<RubricGrid["useMarkRange"]>("rubricUseMarkRange", { required: true });
const rubricCapabilityMarkType = defineModel<MarkTypeSlug | null>("rubricCapabilityMarkType", {
    required: true,
});
const rubricCapabilityGradeGroup = defineModel<RubricGrid["capabilityGradeGroup"]>("rubricCapabilityGradeGroup", {
    required: true,
});

const rubricWeight = window.Vue.computed({
    get: () => rubricWeightModel.value ?? "",
    set: (val: number | "") => {
        rubricWeightModel.value = val === "" ? null : val;
    },
});

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
});
</script>

<template>
    <!-- TODO: show all of these when courseManagedReadonly -->
    <div class="row">
        <div class="small-12 columns">
            <label>
                <span class="required-with-tip">Rubric Title</span>
                <span
                    data-tooltip
                    class="hint has-tip tip-top"
                    :title="`Add a title so that users can identify this rubric when reusing rubrics and viewing the ${locale.activity.singular()}.`"
                >
                    ?
                </span>
                <input v-model.lazy="rubricTitle" type="text" :name="`rubric-${props.rubricId}-title`" required />
            </label>
        </div>
    </div>

    <div class="row">
        <div class="small-12 columns">
            <label>
                Rubric Weighting
                <span
                    data-tooltip
                    class="hint has-tip tip-top"
                    :title="`This value indicates how much this rubric's scoring contributes to the ${locale.grades.singular()} for the ${locale.activity.singular()} - a value of 0 indicates this rubric has no impact on the ${locale.grades.singular()}. Generally, this is a number between 0 and 100. To contribute to the student weighted average ${locale.grades.singular()} for the ${locale.class.singular()}, a value must be applied to the ${locale.activity.singular()} weighting.`"
                >
                    ?
                </span>
                <input
                    v-model.number.lazy="rubricWeight"
                    type="number"
                    min="0"
                    :name="`rubric-${props.rubricId}-weight`"
                />
            </label>
        </div>
    </div>

    <mark-type-input
        v-model:selected-mark-type="rubricCapabilityMarkType"
        v-model:selected-grade-group="rubricCapabilityGradeGroup"
        :context="`rubric-${props.rubricId}-capability`"
        :all-mark-types="props.capabilityMarkTypes"
        :all-grade-schemes="props.capabilityGradingSchemes"
        :default-grade-group="props.defaultGradeGroup"
        :render-max-mark="false"
        :course-managed-readonly="/* TODO */ false"
        :mark-type-label="`${locale.rubricGroup.proper()} Mark Type`"
    />

    <div class="row">
        <div class="small-12 columns">
            <div id="useMarkRangePanel">
                <label :for="`rubric-${props.rubricId}-useMarkRange`" class="inline-block">Use Mark Range</label>
                <div class="switch">
                    <input
                        :id="`rubric-${props.rubricId}-useMarkRange`"
                        v-model="rubricUseMarkRange"
                        :name="`rubric-${props.rubricId}-useMarkRange`"
                        type="checkbox"
                        data-rubric-use-mark-range-toggle
                    />
                    <label :for="`rubric-${props.rubricId}-useMarkRange`">
                        <span><sbx-icon name="approve" size="m" color="currentColor"></sbx-icon></span>
                        <span><sbx-icon name="close" size="m" color="currentColor"></sbx-icon></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>
