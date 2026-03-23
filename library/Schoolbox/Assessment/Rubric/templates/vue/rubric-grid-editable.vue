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
import type { RubricGrid, RubricGridLevel } from "./types";

interface Props {
    rubricGridPayload: RubricGrid;
    maxLevels: number;
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
                        <!-- TODO: data-spawn="group" data-group-grid-text -->
                        <textarea
                            :placeholder="`+ Type to add new ${locale.rubricGroup.singular()}`"
                            :maxlength="MAX_CAPABILITY_LENGTH"
                        ></textarea>
                    </th>
                </tr>
            </tbody>
        </table>
    </div>
</template>
