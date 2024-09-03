<script setup lang="ts">
/**
 * The empty state for a curriculum overview component when there are no curriculum codes present
 */
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import type { CurriculumOverviewComponent, Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";

interface Props {
    unit: Unit;
    column: Column;
}
const props = defineProps<Props>();

const curriculumStore = useUnitPlannerCurriculumStore();
const component = window.Vue.ref<CurriculumOverviewComponent | null>(
    curriculumStore.getComponent(props.unit.curriculum.components, props.column),
);
const isCreating = window.Vue.ref(false);

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

/**
 * To open the curriculum code mapping modal,
 * ensuring the relevant selected nodes are displayed by
 * passing in the folderId and componentId to target the appropriate folderNodesStore
 */
function onModifySelected() {
    if (component.value === null) {
        return;
    }
    curriculumStore.openCurriculumSelectorModal(props.unit.folderId, component.value.componentId);
}

/**
 * To create a new curriculum component, adding it to the relevant units homepage
 */
async function onCreateSelected() {
    if (isCreating.value) {
        return;
    }
    isCreating.value = true;
    await curriculumStore.createComponent(props.unit, props.column);
    await window.Vue.nextTick();
    isCreating.value = false;

    // Open the curriculum selector modal after creating the component
    // This avoids the need to click the modify button again to open the modal,
    // as the user has effectively already indicated they want to modify the selected nodes
    onModifySelected();
}

/**
 * When the Map curriculum codes link is clicked
 * Pick the right edit type based on whether the component is created
 */
async function onMapSelected() {
    if (component.value === null) {
        await onCreateSelected();
    } else {
        onModifySelected();
    }
}
</script>

<template>
    <i class="icon-curriculum"></i>
    <p v-if="props.unit.curriculum._routes.createComponent && props.unit.curriculum._routes.modifyCurriculum">
        <a @click.prevent="onMapSelected">Select the curriculum codes</a>
        that you intend to cover in this area of the curriculum
    </p>
    <p v-else>No curriculum codes have been added to this unit.</p>
</template>

<style scoped></style>
