<script setup lang="ts">
/**
 * The rubric builder shown on the activity create/modify page
 * (currently just for due work, soon to be more)
 *
 * This creates a form element for each rubric with the name "rubric[${rubricId}]"
 * which contains a JSON-encoded version of the rubric grid for the backend to process
 * TODO: multiple rubric support comes soon
 */

import { cloneDeep } from "es-toolkit";
import { MARK_TYPE_VALUE_NONE } from "@schoolboxAssessment/Types/MarkType";
import { RUBRIC_GRID_IMPORTED } from "./event";
import { translateGridToFormPayload } from "./payload";
import type { RubricGrid } from "./types";
import RubricGridEditable from "./rubric-grid-editable.vue";

interface Props {
    rubricGridPayload: RubricGrid;
    maxLevels: number;
    folderId: number;
    routeRubricCreate: string;
}
const props = defineProps<Props>();

const rubricGrid = window.Vue.ref(cloneDeep(props.rubricGridPayload)); // local mutable copy

const useMarkRange = window.Vue.ref<boolean>(false);
const capabilityMarkType = window.Vue.ref<number>(MARK_TYPE_VALUE_NONE);

/**
 * Keep the "Use mark range" checkbox in sync with the ref
 */
function onUseMarkRangeChange(this: HTMLInputElement): void {
    useMarkRange.value = this.checked;
}

/**
 * Keep the capability mark type input (which is outside of vue's scope) in sync with the ref
 * TODO: ideally this would be fully handled in vue, but markType.twig is currently rendered via twig & jquery
 */
function onCapabilityMarkTypeChange(this: HTMLSelectElement): void {
    capabilityMarkType.value = parseInt(this.value, 10);
}

window.Vue.onMounted(() => {
    // TODO: ideally deal with this fully inside of vue instead
    const useMarkRangeInput = document.getElementById("useMarkRange");
    if (!(useMarkRangeInput instanceof HTMLInputElement)) {
        console.warn("Unable to find 'Use mark range' input");
        return;
    }

    onUseMarkRangeChange.call(useMarkRangeInput);
    useMarkRangeInput.addEventListener("change", onUseMarkRangeChange);

    // TODO: ideally deal with this fully inside of vue instead
    const markTypeInput = document.getElementById("capability-markType");
    if (!(markTypeInput instanceof HTMLSelectElement)) {
        console.warn("Unable to find capability mark type input");
        return;
    }

    onCapabilityMarkTypeChange.call(markTypeInput);
    markTypeInput.addEventListener("change", onCapabilityMarkTypeChange);
});

window.Vue.onUnmounted(() => {
    // clean up
    const useMarkRangeInput = document.getElementById("useMarkRange");
    useMarkRangeInput?.removeEventListener("change", onUseMarkRangeChange);

    const markTypeInput = document.getElementById("capability-markType");
    markTypeInput?.removeEventListener("change", onCapabilityMarkTypeChange);
});

/**
 * Calculates the assessment's max available marks from the rubric grid
 * based on the max value for each capability, instead of the max descriptor value for each indicator.
 */
const rubricSum = window.Vue.computed(() => {
    let sum = 0;
    for (const capability of rubricGrid.value.capabilities) {
        if (capability.maxValue !== null) {
            sum += capability.maxValue;
        }
    }
    return parseFloat(sum.toFixed(2));
});

/**
 * Assuming the assessment uses the "Total Mark" mark type (should always update even if not as the mark type could change),
 * Keep the max mark input in sync with the rubric grid's max value
 */
window.Vue.watch(rubricSum, (newValue) => {
    // TODO: note this has consequences for multiple rubrics later on, it'll need to change slightly

    // TODO: ideally deal with this fully inside of vue instead
    const maxMarkInput = document.getElementById("maxMark");
    if (maxMarkInput instanceof HTMLInputElement) {
        maxMarkInput.value = newValue.toString();
    }

    const instructorMaxMarkInput = document.getElementById("instructor-maxMark");
    if (instructorMaxMarkInput instanceof HTMLInputElement) {
        instructorMaxMarkInput.value = newValue.toString();
    }
});

/**
 * Listen for a rubric grid import event
 * TODO: this logic will need to change for multiple rubrics
 */
document.addEventListener(RUBRIC_GRID_IMPORTED, (event) => {
    rubricGrid.value = event.detail.rubricGrid;
});
</script>

<template>
    <rubric-grid-editable
        v-model:rubric-grid="rubricGrid"
        :folder-id="props.folderId"
        :max-levels="props.maxLevels"
        :capability-mark-type="capabilityMarkType"
        :use-mark-range="useMarkRange"
        :route-rubric-create="props.routeRubricCreate"
    />

    <!-- hidden input to submit the rubric grid as JSON -->
    <input
        type="hidden"
        :name="`rubric[${rubricGrid.id}]`"
        :value="JSON.stringify(translateGridToFormPayload(rubricGrid))"
    />
</template>
