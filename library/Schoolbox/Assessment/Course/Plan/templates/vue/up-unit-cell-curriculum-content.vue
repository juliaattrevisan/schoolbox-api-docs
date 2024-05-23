<script setup lang="ts">
/**
 * To display a curriculum overview component within the unit planner
 */

import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import UpUnitCellCurriculumContentNodes from "./up-unit-cell-curriculum-content-nodes.vue";
import type { CurriculumOverviewComponent, Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";

interface Props {
    unit: Unit; // the unit linked to the curriculum cell
    column: Column; // the column linked to the curriculum cell
}
const props = defineProps<Props>();

const curriculumStore = useUnitPlannerCurriculumStore();
const component = window.Vue.ref<CurriculumOverviewComponent | null>(
    curriculumStore.getComponent(props.unit.curriculum.components, props.column),
);

/**
 * To respond to a new curriculum component being created
 */
window.Vue.watch(
    () => props.unit.curriculum.components,
    () => {
        component.value = curriculumStore.getComponent(props.unit.curriculum.components, props.column);
    },
    { deep: true },
);
</script>

<template>
    <up-unit-cell-curriculum-content-nodes
        v-if="component !== null"
        :selected-nodes="props.unit.curriculum.selectedNodes"
        :nodes="props.unit.curriculum.nodes ?? []"
        :folder-id="props.unit.folderId"
        :component="component"
    />
</template>

<style scoped></style>
