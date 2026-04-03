<script setup lang="ts">
/**
 * Renders two dropdowns corresponding to a set of Mark Type instances and
 * a set of Grade Group instances. The latter conditionally shows depending
 * on the setting of the former.
 *
 * This is equivalent to /library/Schoolbox/Learning/Assessment/templates/fragments/markType.twig
 * except without dual mark types for now (that'd likely be done in a parent component anyway)
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import type { MarkType } from "@schoolboxAssessment/Types/MarkType";

export type GradeScheme = {
    id: number;
    name: string;
    gradeLevels: {
        lowerBound: number;
        caption: string;
    }[];
};

interface Props {
    /** A string prefix for namespacing, so you can reuse this fragment in the same template */
    context: string;
    /** available mark types */
    allMarkTypes: MarkType[];
    /** tuples for (id, name) of grading schemes */
    allGradeSchemes: GradeScheme[];
    /** Set in admin via default_grade_group config var */
    defaultGradeGroup: number;
    /** True allows the maxMark input field to exist */
    renderMaxMark: boolean;
    /** The label text for the mark type selector */
    markTypeLabel?: string;
    /** The tooltip text for the mark type selector (optional) */
    markTypeTooltip?: string;
    /** A bool to decide if this is readonly or not */
    courseManagedReadonly: boolean;
}
const props = withDefaults(defineProps<Props>(), {
    markTypeLabel: "Mark Type",
    markTypeTooltip: undefined,
});

const idPrefix = window.Vue.computed(() => (props.context ? `${props.context}-` : ""));

/** The maximum mark currently selected */
const selectedMaxMarkModel = defineModel<number | null>("selectedMaxMark", { required: false, default: null });
const selectedMaxMark = window.Vue.computed({
    get: () => selectedMaxMarkModel.value ?? "",
    set: (val: number | "") => {
        selectedMaxMarkModel.value = val === "" ? null : val;
    },
});

/** The mark type currently selected */
const selectedMarkTypeModel = defineModel<MarkTypeSlug | null>("selectedMarkType", { required: true });
const selectedMarkType = window.Vue.computed({
    get: () => selectedMarkTypeModel.value ?? MarkTypeSlug.NONE,
    set: (val: MarkTypeSlug | "") => {
        selectedMarkTypeModel.value = val === "" ? null : val;
    },
});

/** The grade group currently selected */
const selectedGradeGroupModel = defineModel<number | null>("selectedGradeGroup", { required: true });
const selectedGradeGroupId = window.Vue.computed({
    get: () => selectedGradeGroupModel.value ?? props.defaultGradeGroup,
    set: (val: number | "") => {
        selectedGradeGroupModel.value = val === "" ? null : val;
    },
});

const selectedGradeGroup = window.Vue.computed(() => {
    const selectedId = selectedGradeGroupId.value ?? props.defaultGradeGroup ?? null;
    if (selectedId === null) {
        return null;
    }

    const scheme = props.allGradeSchemes.find((s) => s.id === selectedId) ?? null;
    if (!scheme) {
        return null;
    }

    const levels: (GradeScheme["gradeLevels"][number] & { boundText: string })[] = [];
    for (const [index, level] of scheme.gradeLevels.entries()) {
        let boundText: string = level.lowerBound.toString();
        if (index === 0 && level.lowerBound !== 100) {
            boundText += "+";
        } else {
            const nextLevel = scheme.gradeLevels[index + 1];
            if (nextLevel && nextLevel.lowerBound - 0.01 > level.lowerBound) {
                boundText += `-${(nextLevel.lowerBound - 0.01).toFixed(2)}`;
            }
        }

        levels.push({ ...level, boundText });
    }

    return {
        ...scheme,
        gradeLevels: levels,
    };
});

const isGradeScaleModalOpen = window.Vue.ref(false);

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
});
</script>

<template>
    <div class="row">
        <div class="small-12 medium-6 columns">
            <label>
                <span :id="`${idPrefix}markTypeLabel`">{{ props.markTypeLabel }}</span>
                <span
                    v-if="props.markTypeTooltip"
                    :id="`${idPrefix}markTypeTooltip`"
                    data-tooltip
                    class="hint has-tip tip-top"
                    :title="props.markTypeTooltip"
                >
                    ?
                </span>

                <select
                    :id="`${idPrefix}markType`"
                    v-model="selectedMarkType"
                    :name="`${idPrefix}markType`"
                    :data-test="`assessment-form-select-${idPrefix}markType`"
                    :readonly="props.courseManagedReadonly || undefined"
                >
                    <option
                        v-for="markType in props.allMarkTypes"
                        :key="markType.slug"
                        :value="markType.slug"
                        :disabled="(props.courseManagedReadonly && markType.slug !== selectedMarkType) || undefined"
                    >
                        {{ markType.name }}
                    </option>
                </select>
            </label>
        </div>

        <div
            v-if="props.renderMaxMark"
            v-show="selectedMarkType === MarkTypeSlug.TOTAL"
            class="small-12 medium-6 columns"
        >
            <label :for="`${idPrefix}maxMark`">
                Maximum Available Marks
                <input
                    :id="`${idPrefix}maxMark`"
                    v-model.number="selectedMaxMark"
                    type="text"
                    :name="`${idPrefix}maxMark`"
                    :readonly="props.courseManagedReadonly || undefined"
                />
            </label>
        </div>

        <div v-show="selectedMarkType === MarkTypeSlug.LETTER" class="small-12 medium-6 columns">
            <label :for="`${idPrefix}gradingScheme`">{{ locale.grading.proper() }} Scheme</label>
            <div class="input-group">
                <select
                    :id="`${idPrefix}gradingScheme`"
                    v-model.number="selectedGradeGroupId"
                    :name="`${idPrefix}gradingScheme`"
                    :readonly="props.courseManagedReadonly || undefined"
                >
                    <option
                        v-for="scheme in props.allGradeSchemes"
                        :key="scheme.id"
                        :value="scheme.id"
                        :disabled="
                            (props.courseManagedReadonly &&
                                scheme.id.toString() !==
                                    (selectedGradeGroupId?.toString() ?? props.defaultGradeGroup.toString())) ||
                            undefined
                        "
                    >
                        {{ scheme.name }}
                    </option>
                </select>
                <button
                    type="button"
                    class="button"
                    :data-reveal-id="`${idPrefix}gradeScale`"
                    @click="isGradeScaleModalOpen = true"
                >
                    Show {{ locale.grades.proper() }} Scale
                </button>
            </div>
        </div>
    </div>

    <basic-modal v-if="selectedGradeGroup !== null" v-model:is-open="isGradeScaleModalOpen" has-background-click>
        <h2>{{ selectedGradeGroup.name }}</h2>
        <div class="scrollable">
            <table>
                <tbody>
                    <tr v-for="gradeLevel in selectedGradeGroup.gradeLevels" :key="gradeLevel.lowerBound">
                        <td>{{ gradeLevel.boundText }}</td>
                        <td>{{ gradeLevel.caption }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </basic-modal>
</template>
