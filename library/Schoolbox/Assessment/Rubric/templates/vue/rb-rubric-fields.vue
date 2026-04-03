<script setup lang="ts">
/**
 * Editable fields shown above the rubric grid
 */

import locale from "@js/terminology/rosettaStone";
import type { RubricGrid } from "./types";

interface Props {
    rubricId: RubricGrid["id"];
}
const props = defineProps<Props>();

const rubricTitle = defineModel<RubricGrid["title"]>("rubricTitle", { required: true });
const rubricWeightModel = defineModel<RubricGrid["weight"]>("rubricWeight", { required: true });

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
                    :title="`This value indicates how much this rubric's scoring contributes to the ${locale.grades.singular()} for the ${locale.activity.singular()} - a value of 0 indicates this rubric has no impact on the ${locale.grades.singular()}. Generally, this is a number between0 and 100. To contribute to the student weighted average ${locale.grades.singular()} for the ${locale.class.singular()}, a value must be applied to the ${locale.activity.singular()} weighting.`"
                >
                    ?
                </span>
                <input v-model.number.lazy="rubricWeight" type="number" :name="`rubric-${props.rubricId}-weight`" />
            </label>
        </div>
    </div>
</template>
