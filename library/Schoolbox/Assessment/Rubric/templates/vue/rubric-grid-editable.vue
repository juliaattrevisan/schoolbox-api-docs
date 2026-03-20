<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import LevelEditable from "./level-editable.vue";
import type { RubricGrid, RubricGridLevel } from "./types";

/**
 * maxCapabilityLength is 900, due to the way browsers look at maxlength as character lenght, but mysql table looks at char byte length
 * so if there are bullet points in the textbox they count as 3 characters, and will cause issues, so lets work on 900 as the limit, and we shouldn't hit the db max..
 */
const MAX_CAPABILITY_LENGTH = 900;

interface Props {
    rubricGrid: RubricGrid;
    maxLevels: number;
}
const props = defineProps<Props>();

const rubricGridData = window.Vue.ref(props.rubricGrid); // local mutable copy

/**
 * Move a level left or right in the levels array
 * @param level the level to move
 * @param direction the direction to move
 */
function moveLevel(level: RubricGridLevel, direction: "left" | "right"): void {
    const index = rubricGridData.value.levels.findIndex((l) => l.id === level.id);
    const newIndex = direction === "left" ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= rubricGridData.value.levels.length) {
        return; // out of bounds
    }

    const levels = rubricGridData.value.levels;
    [levels[index], levels[newIndex]] = [levels[newIndex], levels[index]];
}

/**
 * Adds a level to the grid, on the right-hand side
 */
function addLevel(): void {
    // TODO: other logic from RubricEditable.prototype.addLevel
    const lowestId = Math.min(...rubricGridData.value.levels.map((l) => l.id), 0);
    const newLevel: RubricGridLevel = {
        id: lowestId - 1, // negative IDs are unsaved
        name: "",
    };
    rubricGridData.value.levels.push(newLevel);
}
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
                        v-for="(level, index) in rubricGridData.levels"
                        :key="level.id"
                        v-model:level-name="level.name"
                        :can-move-left="index > 0"
                        :can-move-right="index < rubricGridData.levels.length - 1"
                        @move="(direction) => moveLevel(level, direction)"
                    />
                    <th v-if="rubricGridData.levels.length < props.maxLevels">
                        <a href="#" data-test="rubric-spawn-level" @click.prevent="addLevel">
                            &plus; {{ locale.rubricLevel.proper() }}
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <!-- TODO: groups -->
            </tbody>
        </table>
    </div>
</template>
