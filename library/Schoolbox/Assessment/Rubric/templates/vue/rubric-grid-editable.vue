<script setup lang="ts">
/**
 * The editable rubric grid shown on the activity create/modify page (currently due work, soon to be more)
 */

import { cloneDeep } from "es-toolkit";
import type { Ref } from "vue";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { assertUnreachable } from "@js/util/errorUtils";
import locale from "@js/terminology/rosettaStone";
import { shiftArrayItem } from "@js/util/arrayUtils";
import { MARK_TYPE_VALUE_NONE } from "@schoolboxAssessment/Types/MarkType";
import LevelEditable from "./level-editable.vue";
import CapabilityEditable from "./capability-editable.vue";
import { MAX_CAPABILITY_LENGTH } from "./types";
import type { RubricGrid, RubricGridLevel, RubricGridCapability, RubricGridIndicator } from "./types";

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

const isCreating = window.Vue.ref(false); // whether we're currently creating a new capability/indicator
const newCapabilityName = window.Vue.ref("");

// async function onAddKeydown(event: KeyboardEvent, type: "group", capabilityId: null): Promise<void>;
// async function onAddKeydown<TType extends "group" | "level" | "criterion">(
//     event: KeyboardEvent,
//     type: TType,
//     capabilityId: TType extends "group" ? null : number,
// ): Promise<void> {

/**
 * When a key is pressed in the "add capability" or "add indicator" textarea,
 * if it's a non-shift return (i.e. shift+return is ignored), add a new capability/indicator to the grid
 * @param event the keydown event
 * @param type the type of item to add, either capability or indicator
 * @param capability if adding an indicator, the capability to add the new item to (will push to capability.indicators)
 * @param nameRef a ref pointing to the name of the new item
 */
async function onAddKeydown<TType extends "capability" | "indicator">(
    event: KeyboardEvent,
    type: TType,
    capability: TType extends "indicator" ? RubricGridCapability : null,
    nameRef: Ref<string, string>,
): Promise<void> {
    if (event.key !== "Enter" || event.shiftKey) {
        return;
    }

    event.preventDefault();

    const name = nameRef.value.trim();
    if (name.length === 0 || isCreating.value) {
        return; // prevent blank and double-fire from keydown to blue
    }

    isCreating.value = true; // prevent mods & double-fire
    nameRef.value = "";

    /**
     * Ensure user is not trying to add a duplicate group. The server-side validation will prevent
     * duplicate criterion, but cannot prevent duplicate groups as they are not persisted until a indicator
     * name is entered.
     */
    const normalisedName = normaliseForComparison(name);
    const nameLocale = type === "capability" ? locale.rubricGroup : locale.rubricCriterion;

    const toast = window.sbProps.toastActivate(`Creating ${nameLocale.singular()}...`);
    if (rubricGrid.value.capabilities.some((c) => normaliseForComparison(c.name) === normalisedName)) {
        toast.timeout({
            text: `${nameLocale.proper()} name already exists!`,
            isSuccess: false,
        });
        return;
    }

    const response = await apiRequest<TType extends "capability" ? RubricGridCapability : RubricGridIndicator>({
        url: props.routeRubricCreate,
        method: "POST",
        body: {
            type: type === "capability" ? "group" : "criterion", // todo: swap for the new names in the backend?
            rubricId: rubricGrid.value.id,
            groupId: type === "indicator" ? capability!.id : null,
            groupIndex: 0, // todo: don't need this anymore... it's only used to render the old twig stuff
            name,
            levelCount: rubricGrid.value.levels.length,
            folderId: props.folderId,
            groupName: type === "indicator" ? capability!.name : null,
        },
    });

    isCreating.value = false;
    if (!response.success) {
        toast.timeout({ text: response.message, isSuccess: false });
        return;
    }

    toast.timeout({ text: `${nameLocale.proper()} created!` });

    if (type === "capability") {
        // TType is "capability", so response is RubricGridCapability
        // TODO: this assumes the API returns the new capability struct, that is definitely not the case right now
        rubricGrid.value.capabilities.push(response.data as RubricGridCapability);
    } else if (type === "indicator") {
        // TType is "indicator", so response is RubricGridIndicator
        // TODO: this assumes the API returns the new indicator struct, that is definitely not the case right now
        capability!.indicators.push(response.data as RubricGridIndicator);
    } else {
        assertUnreachable(type);
    }
}

/**
 * When the return key is pressed, add a new capability to the grid
 * (this needs to exist as the ref can't be passed directly when inside <template>)
 * @param event the keydown event
 */
async function onAddCapabilityKeydown(event: KeyboardEvent): Promise<void> {
    await onAddKeydown(event, "capability", null, newCapabilityName);
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
                    :is-creating="isCreating"
                    :can-move-prev="index > 0"
                    :can-move-next="index < rubricGrid.capabilities.length - 1"
                    @move="(dir) => shiftArrayItem(rubricGrid.capabilities, index, dir)"
                    @add-indicator-keydown="(e, nameRef) => onAddKeydown(e, 'indicator', capability, nameRef)"
                />

                <tr class="rb-group-header rb-new-group">
                    <!-- Add a new group -->
                    <th :colspan="totalColumns">
                        <!-- TODO: previously some of these only added maxlength after the first change (data-group-grid-text in grid-editable.twig) -->
                        <textarea
                            :v-model="newCapabilityName"
                            :placeholder="`+ Type to add new ${locale.rubricGroup.singular()}`"
                            :maxlength="MAX_CAPABILITY_LENGTH"
                            :disabled="isCreating"
                            @keydown="(e) => onAddCapabilityKeydown(e)"
                        ></textarea>
                    </th>
                </tr>
            </tbody>
        </table>
    </div>
</template>
