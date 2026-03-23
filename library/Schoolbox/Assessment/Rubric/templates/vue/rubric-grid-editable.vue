<script setup lang="ts">
/**
 * The editable rubric grid shown on the activity create/modify page (currently due work, soon to be more)
 */

import { cloneDeep } from "es-toolkit";
import locale from "@js/terminology/rosettaStone";
import { shiftArrayItem } from "@js/util/arrayUtils";
import { MARK_TYPE_VALUE_NONE } from "@schoolboxAssessment/Types/MarkType";
import LevelEditable from "./level-editable.vue";
import CapabilityEditable from "./capability-editable.vue";
import { MAX_CAPABILITY_LENGTH } from "./types";
import type { RubricGrid, RubricGridLevel, RubricGridCapability } from "./types";
import { apiRequest } from "@js/util/sbx/apiUtils";

interface Props {
    rubricGridPayload: RubricGrid;
    maxLevels: number;
    folderId: number;
    routeRubricCreate: string;
}
const props = defineProps<Props>();

const rubricGrid = window.Vue.ref(cloneDeep(props.rubricGridPayload)); // local mutable copy

/**
 * Move a level left or right
 * @param index the index of the level to move
 * @param direction the direction to move
 */
function moveLevel(index: number, direction: -1 | 1): void {
    shiftArrayItem(rubricGrid.value.levels, index, direction);

    // todo: handle moving all the descriptors around
}

/**
 * Adds a level to the grid, on the right-hand side
 */
function addLevel(): void {
    // TODO: other logic from RubricEditable.prototype.addLevel
    const lowestId = Math.min(...rubricGrid.value.levels.map((l) => l.id), 0);
    const newLevel: RubricGridLevel = {
        id: lowestId - 1, // negative IDs are unsaved
        name: "",
    };
    rubricGrid.value.levels.push(newLevel);

    // todo: handle adding new descriptors
}

/**
 * Returns the normalised string for comparison to avoid duplicates.
 * MUST use same logic as RubricMapper->normaliseForComparison()
 * @param string the string to normalise
 * @returns normalised string
 */
function normaliseForComparison(string: string): string {
    // Ensure var really is a string before performing string operations
    return string.toString().replace(/\s+/g, " ").trim().toLowerCase();
}

const isAddingCapability = window.Vue.ref(false);
const newCapabilityName = window.Vue.ref("");

/**
 * When a key is pressed in the "add capability" textarea,
 * if it's a non-shift return (i.e. shift+return is ignored), add a new capability to the grid
 * @param event the keydown event
 */
async function onAddCapabilityKeydown(event: KeyboardEvent): Promise<void> {
    if (event.key !== "Enter" || event.shiftKey) {
        return;
    }

    event.preventDefault();
    const name = newCapabilityName.value.trim();

    if (name.length === 0 || isAddingCapability.value) {
        return; // prevent blank and double-fire from keydown to blue
    }

    isAddingCapability.value = true; // prevent mods & double-fire
    newCapabilityName.value = "";

    /**
     * Ensure user is not trying to add a duplicate group. The server-side validation will prevent
     * duplicate criterion, but cannot prevent duplicate groups as they are not persisted until a indicator
     * name is entered.
     */
    const normalisedName = normaliseForComparison(name);

    const toast = window.sbProps.toastActivate(`Creating ${locale.rubricGroup.singular()}...`);
    if (rubricGrid.value.capabilities.some((c) => normaliseForComparison(c.name) === normalisedName)) {
        toast.timeout({
            text: `${locale.rubricGroup.proper()} name already exists!`,
            isSuccess: false,
        });
        return;
    }

    const response = await apiRequest<RubricGridCapability>({
        url: props.routeRubricCreate,
        method: "POST",
        body: {
            type: "group",
            rubricId: rubricGrid.value.id,
            groupId: null, // adding a new capability
            groupIndex: 0, // todo: don't need this anymore... it's only used to render the old twig stuff
            name,
            levelCount: rubricGrid.value.levels.length,
            folderId: props.folderId,
        },
    });

    isAddingCapability.value = false;
    if (!response.success) {
        toast.timeout({ text: response.message, isSuccess: false });
        return;
    }

    toast.timeout({ text: `${locale.rubricGroup.proper()} created!` });
    // TODO: this assumes the API returns the new capability struct, that is definitely not the case right now
    rubricGrid.value.capabilities.push(response.data);
}

/**
 * The total number of columns in the grid
 */
const totalColumns = window.Vue.computed(() => {
    let columns = rubricGrid.value.levels.length + 2; // levels + capability + criterion columns

    if (rubricGrid.value.levels.length < props.maxLevels) {
        columns += 1; // "add level" button
    }

    return columns;
});

const capabilityMarkType = window.Vue.ref<number>(MARK_TYPE_VALUE_NONE);

/**
 * Keep the capability mark type input (which is outside of vue's scope) in sync with the ref
 * TODO: ideally this would be fully handled in vue, but markType.twig is currently rendered via twig & jquery
 */
function onCapabilityMarkTypeChange(this: HTMLSelectElement): void {
    capabilityMarkType.value = parseInt(this.value, 10);
}

window.Vue.onMounted(() => {
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
</script>

<template>
    <h3 class="subheader show-for-medium-down">Levels</h3>
    <div class="scrollable">
        <!-- TODO: :id="`rb-${rubricGrid.id}`" :data-rubric="rubricGrid.id" data-rb-grid -->
        <table class="rb stack-for-medium-down">
            <thead>
                <tr>
                    <th class="rb-no-cell">
                        <!-- capabilities -->
                        {{ locale.rubricGroup.properPlural() }}
                    </th>
                    <th class="rb-no-cell">
                        <!-- indicators -->
                        {{ locale.rubricCriterion.properPlural() }}
                    </th>
                    <level-editable
                        v-for="(level, index) in rubricGrid.levels"
                        :key="level.id"
                        v-model:name="level.name"
                        :can-move-prev="index > 0"
                        :can-move-next="index < rubricGrid.levels.length - 1"
                        @move="(dir) => moveLevel(index, dir)"
                    />
                    <th v-if="rubricGrid.levels.length < props.maxLevels">
                        <!-- Add a new level -->
                        <a href="#" data-test="rubric-spawn-level" @click.prevent="addLevel">
                            &plus; {{ locale.rubricLevel.proper() }}
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <capability-editable
                    v-for="(capability, index) in rubricGrid.capabilities"
                    :key="capability.id"
                    v-model:name="capability.name"
                    v-model:indicators="capability.indicators"
                    v-model:max-value="capability.maxValue"
                    :total-columns="totalColumns"
                    :levels="rubricGrid.levels"
                    :max-levels="props.maxLevels"
                    :capability="capability"
                    :capability-mark-type="capabilityMarkType"
                    :can-move-prev="index > 0"
                    :can-move-next="index < rubricGrid.capabilities.length - 1"
                    @move="(dir) => shiftArrayItem(rubricGrid.capabilities, index, dir)"
                />

                <tr class="rb-group-header rb-new-group">
                    <!-- Add a new group -->
                    <th :colspan="totalColumns">
                        <!-- TODO: previously some of these only added maxlength after the first change (data-group-grid-text in grid-editable.twig) -->
                        <textarea
                            :v-model="newCapabilityName"
                            :placeholder="`+ Type to add new ${locale.rubricGroup.singular()}`"
                            :maxlength="MAX_CAPABILITY_LENGTH"
                            :disabled="isAddingCapability"
                            @keydown="onAddCapabilityKeydown"
                        ></textarea>
                    </th>
                </tr>
            </tbody>
        </table>
    </div>
</template>
