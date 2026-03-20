<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import LevelEditable from "./level-editable.vue";
import type { RubricGrid } from "./types";

/**
 * maxCapabilityLength is 900, due to the way browsers look at maxlength as character lenght, but mysql table looks at char byte length
 * so if there are bullet points in the textbox they count as 3 characters, and will cause issues, so lets work on 900 as the limit, and we shouldn't hit the db max..
 */
const MAX_CAPABILITY_LENGTH = 900;

interface Props {
    rubricGrid: RubricGrid;
}
const props = defineProps<Props>();
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
                        v-for="level in props.rubricGrid.levels"
                        :key="level.id"
                        v-model:level-name="level.name"
                    />
                    <th>
                        <!-- TODO: data-spawn="level" -->
                        <a href="#" data-test="rubric-spawn-level">&plus; {{ locale.rubricLevel.proper() }}</a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <!-- TODO: groups -->
            </tbody>
        </table>
    </div>
</template>
