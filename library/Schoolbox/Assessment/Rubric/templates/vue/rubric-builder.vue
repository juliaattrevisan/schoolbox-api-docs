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
import FieldsetCollapsible from "@schoolboxVue/components/fieldset-collapsible.vue";
import { RUBRIC_GRID_IMPORTED } from "./event";
import { translateGridToFormPayload } from "./payload";
import type { RubricGrid } from "./types";
import RubricGridEditable from "./rubric-grid-editable.vue";

interface Props {
    rubricGridsPayload: RubricGrid[];
    rubricBlankGrid: RubricGrid;
    maxLevels: number;
    folderId: number;
    routeRubricCreate: string;
}
const props = defineProps<Props>();

const rubricGrids = window.Vue.ref(cloneDeep(props.rubricGridsPayload)); // local mutable copy

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
 * Calculates the assessment's max available marks from the rubric grid(s)
 * based on the max value for each capability, instead of the max descriptor value for each indicator.
 */
const allRubricsSum = window.Vue.computed(() => {
    let totalSum = 0;
    for (const rubric of rubricGrids.value) {
        let rubricSum = 0;
        for (const capability of rubric.capabilities) {
            if (capability.maxValue !== null) {
                rubricSum += capability.maxValue;
            }
        }

        // TODO: weights
        totalSum += parseFloat(rubricSum.toFixed(2));
    }

    return totalSum;
});

/**
 * Assuming the assessment uses the "Total Mark" mark type (should always update even if not as the mark type could change),
 * Keep the max mark input in sync with the rubric grid's max value
 */
window.Vue.watch(allRubricsSum, (newValue) => {
    if (rubricGrids.value.length === 0) {
        return; // if there are no rubrics, we shouldn't override the max mark field
    }

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
    // TODO: for now this will just add a new rubric
    rubricGrids.value.push(event.detail.rubricGrid);
});

const showRubricCreate = window.Vue.ref(false);

/**
 * Create a new blank rubric ("Create manually" button)
 */
function createRubric(): void {
    // TODO: I assume this is meant to prefill with similar data to the original one.
    // TODO: This might need to fetch a new blank grid since the capabilities/indicators possibly changed and that has implications for the new grid...
    const newRubric = cloneDeep(props.rubricBlankGrid);
    newRubric.id = 0; // apparently 0 is how rubrics define they are new
    rubricGrids.value.push(newRubric);
}
</script>

<template>
    <!-- TODO: temp multiple grids... chaotically -->
    <fieldset-collapsible
        v-for="(rubric, index) in [rubricGrids[0], rubricGrids[0], rubricGrids[0], rubricGrids[0]]"
        :key="rubric.id"
        delay-content-render
    >
        <template #legend>Rubric {{ index + 1 }}</template>

        <template #content>
            <rubric-grid-editable
                v-model:rubric-grid-levels="rubric.levels"
                v-model:rubric-grid-capabilities="rubric.capabilities"
                :rubric-id="rubric.id"
                :folder-id="props.folderId"
                :max-levels="props.maxLevels"
                :capability-mark-type="capabilityMarkType"
                :use-mark-range="useMarkRange"
                :route-rubric-create="props.routeRubricCreate"
            />

            <!-- hidden input to submit the rubric grid as JSON -->
            <input
                type="hidden"
                :name="`rubric[${rubric.id}]`"
                :value="JSON.stringify(translateGridToFormPayload(rubric))"
            />
        </template>
    </fieldset-collapsible>

    <fieldset-collapsible v-if="showRubricCreate" delay-content-render initially-expanded>
        <template #legend>Rubric {{ rubricGrids.length + 1 }}</template>

        <template #content>
            <div class="l-flex-row l-flex--justify l-gap-xs">
                <!-- TODO -->
                <button class="button l-flex__item--1 icon-modify" type="button" @click="createRubric">
                    Create manually
                </button>
                <button class="button l-flex__item--1 icon-copy" type="button">Copy from existing activity</button>
                <button class="button l-flex__item--1 icon-upload" type="button">Upload a Schoolbox format file</button>
            </div>
        </template>
    </fieldset-collapsible>

    <div v-if="!showRubricCreate" class="row">
        <div class="column small-12">
            <button class="button" @click="showRubricCreate = true">Add a rubric</button>
        </div>
    </div>
</template>
