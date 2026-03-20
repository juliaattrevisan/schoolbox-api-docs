<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import LevelEditable from "./level-editable.vue";
import GroupEditable from "./group-editable.vue";
import { MAX_CAPABILITY_LENGTH } from "./types";
import type { RubricGrid, RubricGridGroup, RubricGridLevel } from "./types";

interface Props {
    rubricGridPayload: RubricGrid;
    maxLevels: number;
}
const props = defineProps<Props>();

const rubricGrid = window.Vue.ref(props.rubricGridPayload); // local mutable copy

/**
 * Move a level left or right in the levels array
 * @param level the level to move
 * @param direction the direction to move
 */
function moveLevel(level: RubricGridLevel, direction: -1 | 1): void {
    const index = rubricGrid.value.levels.findIndex((l) => l.id === level.id);
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= rubricGrid.value.levels.length) {
        return; // out of bounds
    }

    const levels = rubricGrid.value.levels;
    [levels[index], levels[newIndex]] = [levels[newIndex], levels[index]];
}

/**
 * Move a group up or down in the groups array
 * @param group the group to move
 * @param direction the direction to move
 */
function moveGroup(group: RubricGridGroup, direction: -1 | 1): void {
    const index = rubricGrid.value.groups.findIndex((g) => g.id === group.id);
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= rubricGrid.value.groups.length) {
        return; // out of bounds
    }

    const groups = rubricGrid.value.groups;
    [groups[index], groups[newIndex]] = [groups[newIndex], groups[index]];
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
                        @move="(direction) => moveLevel(level, direction)"
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
                <group-editable
                    v-for="(group, index) in rubricGrid.groups"
                    :key="group.id"
                    v-model:name="group.name"
                    :total-columns="totalColumns"
                    :group="group"
                    :can-move-prev="index > 0"
                    :can-move-next="index < rubricGrid.groups.length - 1"
                    @move="(direction) => moveGroup(group, direction)"
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
