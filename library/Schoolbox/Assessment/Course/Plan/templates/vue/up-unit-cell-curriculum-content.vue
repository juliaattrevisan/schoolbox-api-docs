<script setup lang="ts">
/**
 * To display a curriculum overview component within the unit planner
 */
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import UpUnitCellCurriculumContentNodes from "./up-unit-cell-curriculum-content-nodes.vue";
import type { CurriculumOverviewComponent, Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";

interface Props {
    unit: Unit; // the unit linked to the curriculum cell
    column: Column; // the column linked to the curriculum cell
}
const props = defineProps<Props>();

const unitPlannerStore = useUnitPlannerStore();

const storeUnit = window.Vue.computed(() => {
    return unitPlannerStore.units.find((unit) => unit.id === props.unit.id);
});

const curriculumStore = useUnitPlannerCurriculumStore();
const component = window.Vue.ref<CurriculumOverviewComponent | null>(
    curriculumStore.getComponent(props.unit.curriculum.components, props.column),
);

/**
 * Is this cell empty?
 * Used by the parent component to determine whether to show the empty state
 */
const isEmpty = window.Vue.ref(false);

window.Vue.onMounted(() => {
    // If there is no component, then the cell is empty
    if (isEmpty.value === false && component.value === null) {
        isEmpty.value = true;
    }
});

/**
 * Update the isEmpty value based on the filtered node state changing from a child component
 * @param hasFilteredNodes Whether there are filtered nodes
 */
function onHasFilteredNodeChange(hasFilteredNodes: boolean): void {
    isEmpty.value = !hasFilteredNodes;
}

// Allow the parent component to identify if the cell is empty
defineExpose({ isEmpty });

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
        v-if="component !== null && storeUnit"
        :selected-nodes="storeUnit.curriculum.selectedNodes"
        :unit="props.unit"
        :nodes="props.unit.curriculum.nodes ?? []"
        :folder-id="props.unit.folderId"
        :component="component"
        @on-has-filtered-node-change="onHasFilteredNodeChange"
    />
</template>

<style scoped></style>
