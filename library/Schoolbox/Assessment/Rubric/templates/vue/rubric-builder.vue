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
import locale from "@js/terminology/rosettaStone";
import { MARK_TYPE_VALUE_NONE } from "@schoolboxAssessment/Types/MarkType";
import FieldsetCollapsible from "@schoolboxVue/components/fieldset-collapsible.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { RUBRIC_GRID_IMPORTED } from "./event";
import { translateGridToFormPayload } from "./payload";
import type { RubricGrid } from "./types";
import RbRubricFields from "./rb-rubric-fields.vue";
import RbRubricGrid from "./rb-rubric-grid.vue";
import RbRubricDownload from "./rb-rubric-download.vue";

interface Props {
    rubricGridsPayload: RubricGrid[];
    rubricBlankGrid: RubricGrid;
    maxLevels: number;
    folderId: number;
    assessmentId: number | null;
    routeRubricCreate: string;
    routeRubricDownload?: string | null;
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
 * Calculates the assessment's total weight from the rubric grids
 */
const totalRubricWeight = window.Vue.computed(() => {
    let totalWeight = 0;
    for (const rubric of rubricGrids.value) {
        totalWeight += rubric.weight ?? 0; // rubrics without a weight are equivalent to 0 weight
    }

    return totalWeight;
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

        let weightMultiplier = 1;
        if (totalRubricWeight.value > 0) {
            // rubrics with no weights don't contribute to the total if weighted rubrics are present
            weightMultiplier = (rubric.weight ?? 0) / totalRubricWeight.value;
        }

        totalSum += parseFloat((rubricSum * weightMultiplier).toFixed(2));
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

    // TODO: backend handling of negative IDs, it previously used "0" to some extent I think?
    const lowestId = Math.min(...rubricGrids.value.map((grid) => grid.id), 0);
    newRubric.id = lowestId - 1; // negative ID represents a new rubric that hasn't been persisted yet

    rubricGrids.value.push(newRubric);
    showRubricCreate.value = false;
}

const deleteConfirmationId = window.Vue.ref<RubricGrid["id"] | null>(null);
const showDeleteConfirmation = window.Vue.computed({
    get: () => deleteConfirmationId.value !== null,
    set: (value: boolean) => {
        if (!value) {
            deleteConfirmationId.value = null;
        }
    },
});

/**
 * Delete a rubric
 * @param rubricId - the ID of the rubric to delete
 * @param confirm - whether to show a confirmation modal before deletion
 */
function deleteRubric(rubricId: number | "create", confirm: boolean = true): void {
    deleteConfirmationId.value = null;
    if (rubricId === "create") {
        showRubricCreate.value = false;
        return;
    }

    if (confirm) {
        deleteConfirmationId.value = rubricId;
        return;
    }

    rubricGrids.value = rubricGrids.value.filter((rubric) => rubric.id !== rubricId);
}

const rubricRows = window.Vue.computed(() => {
    // all the actual rubrics
    const rows: (RubricGrid | { id: "create" })[] = [...rubricGrids.value];

    // plus a blank one used when creating a new rubric that doesn't have a grid yet
    // (it needs an ID to be able to be used in the v-for)
    if (showRubricCreate.value) {
        rows.push({ id: "create" });
    }

    return rows;
});

// TODO: temp multiple grids... chaotically
createRubric();
</script>

<template>
    <div v-for="(rubric, index) in rubricRows" :key="rubric.id" class="actions-small-1 rubric-builder-row">
        <fieldset-collapsible delay-content-render :initially-expanded="rubric.id === 'create' || rubric.id < 0">
            <template #legend>Rubric {{ index + 1 }}</template>

            <!-- existing rubric content -->
            <template v-if="rubric.id !== 'create'" #content>
                <rb-rubric-fields
                    v-model:rubric-title="rubric.title"
                    v-model:rubric-weight="rubric.weight"
                    :rubric-id="rubric.id"
                />

                <rb-rubric-grid
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

            <!-- create a new rubric -->
            <template v-else #content>
                <div class="l-flex-row l-flex--justify l-gap-xs">
                    <button class="button l-flex__item--1 icon-modify" type="button" @click="createRubric">
                        Create manually
                    </button>
                    <!-- TODO -->
                    <!-- <button class="button l-flex__item--1 icon-copy" type="button">Copy from existing activity</button> -->
                    <button class="button l-flex__item--1 icon-upload" type="button">
                        Upload a Schoolbox format file
                    </button>
                </div>
            </template>
        </fieldset-collapsible>

        <nav>
            <sbx-pop-out :content-id="`rubric-${rubric.id}-dropdown`" is-close-on-content-click></sbx-pop-out>
        </nav>
        <ul :id="`rubric-${rubric.id}-dropdown`" class="f-dropdown" data-dropdown-content>
            <li>
                <rb-rubric-download
                    :rubric-grid="rubric.id !== 'create' ? rubric : null"
                    :assessment-id="props.assessmentId"
                    :route-rubric-download="props.routeRubricDownload"
                />
            </li>
            <li>
                <button class="button icon-delete" type="button" @click="() => deleteRubric(rubric.id)">Delete</button>
            </li>
        </ul>
    </div>

    <div v-if="!showRubricCreate" class="row">
        <div class="column small-12">
            <button class="button" type="button" @click="showRubricCreate = true">Add a rubric</button>
        </div>
    </div>

    <basic-modal v-model:is-open="showDeleteConfirmation" has-background-click>
        <h2>Please Confirm</h2>
        <p>Are you sure you wish to delete this rubric from the {{ locale.activity.singular() }}?</p>
        <ul class="flex-list buttons">
            <li v-if="deleteConfirmationId">
                <button
                    class="button submit icon-delete"
                    type="button"
                    @click="deleteRubric(deleteConfirmationId, false)"
                >
                    Delete
                </button>
            </li>
            <li><button class="button" type="button" @click="showDeleteConfirmation = false">Cancel</button></li>
        </ul>
    </basic-modal>
</template>

<style scoped>
/**
 * <fieldset>'s in a mage form have essentially a <hr> added by a top border.
 * To add an inline 3-dot menu, it should be aligned with that line, and
 * a background color is needed to cover up the line which is otherwise be visible behind it.
 */
.rubric-builder-row nav {
    background: var(--sbx-color-foreground-primary, white);
    top: -0.5rem;
}
</style>
