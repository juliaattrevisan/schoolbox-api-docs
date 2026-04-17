<script setup lang="ts">
/**
 * The markable (and readonly) rubric grid
 */

import locale from "@js/terminology/rosettaStone";
import { shiftArrayItem } from "@js/util/arrayUtils";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import RfGridCapability from "./rf-grid-capability.vue";
import { translateGridToFormPayload } from "./payload";
import { MAX_CAPABILITY_LENGTH } from "./types";
import type { RubricGrid, ResultSet } from "./types";

interface Props {
    rubric: RubricGrid;
    folderId: number;
    maxLevels: number;
    capabilityMarkType: MarkTypeSlug;
    assessmentMarkType: MarkTypeSlug;
    resultSets: ResultSet[];
    selfAssess: boolean;
}
const props = defineProps<Props>();

const rubricGridLevels = defineModel<RubricGrid["levels"]>("rubricGridLevels", { required: true });
const rubricGridCapabilities = defineModel<RubricGrid["capabilities"]>("rubricGridCapabilities", { required: true });

const formValueRef = window.Vue.useTemplateRef<HTMLInputElement>("formValueRef");

/** Supress level headings if none have names */
const hasLevels = window.Vue.computed(() => rubricGridLevels.value.some((level) => level.name.trim().length > 0));

/** Emit a change event so jquery.alaressValidate can pick it up for grid-based rules */
const formValue = window.Vue.computed(() => JSON.stringify(translateGridToFormPayload(props.rubric)));
window.Vue.watch(formValue, () => {
    if (formValueRef.value) {
        window.jQuery(formValueRef.value).valid();
    }
});

const showDescriptorValue = window.Vue.computed(() => props.selfAssess);
const showGroupTotal = window.Vue.computed(() => props.selfAssess && props.capabilityMarkType !== MarkTypeSlug.NONE);
const showRubricTotal = window.Vue.computed(() => props.selfAssess);
</script>

<template>
    <!-- hidden input to submit the rubric grid as JSON -->
    <input
        ref="formValueRef"
        type="hidden"
        :name="`rubric[${rubric.id}]`"
        :value="JSON.stringify(translateGridToFormPayload(rubric))"
    />

    <div class="scrollable">
        <table class="rb-markable">
            <thead v-if="hasLevels">
                <tr>
                    <th colspan="2">&nbsp;</th>
                    <th v-for="level in rubricGridLevels" :key="level.id" class="markdown">{{ level.name }}</th>
                </tr>
            </thead>
            <tbody>
                <rf-grid-capability
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
                />

                <!-- {% include '@rubric/group-row-markable.twig' %} -->
            </tbody>

            <!-- {% if showRubricTotal and markType.mode != "none"  %} -->
             <tr v-if="showRubricTotal && props.assessmentMarkType !== MarkTypeSlug.NONE">
                <th class="rb-final-summary" :colspan="rubricGridLevels.length + 2">
                    <!-- TODO: check :data-final-summary="props.rubric.id" -->
                    <!-- TODO: check :data-scaled-mark="rubricMarkedGrid.calculateScaledMark ?? null" -->
                    <!-- TODO: check :data-max="rubricGrid.calculateMaxValueFromCapabilities " -->
                    <!-- TODO: check :data-mark-type="props.assessmentMarkType" markType.mode -->
                    <span

                </th>
            </tr>
        </table>
    </div>
</template>
