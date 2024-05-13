<script setup lang="ts">
/**
 * To display the actions available for the curriculum overview component,
 * which is to modify the selected nodes via the curriculum selector modal
 */

import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnActions from "@schoolboxCurriculum/vue/folderNodes/fn-actions.vue";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import type { CurriculumOverviewComponent, Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";

interface Props {
    unit: Unit; // the unit linked to the curriculum cell
    column: Column; // the column linked to the curriculum cell
}
const props = defineProps<Props>();
let folderNodesStore: ReturnType<typeof useFolderNodesStore>; // the folderNodesStore for the curriculum component, which can be null if no curriculum component is linked yet

const curriculumStore = useUnitPlannerCurriculumStore();
const component = window.Vue.ref<CurriculumOverviewComponent | null>(
    curriculumStore.getCurriculumComponent(props.unit.curriculum.components, props.column),
);
if (component.value !== null) {
    folderNodesStore = useFolderNodesStore(props.unit.folderId, component.value.componentId);
}

/**
 * To open the curriculum code mapping modal,
 * ensuring the relevant selected nodes are displayed by
 * passing in the folderId and componentId to target the appropriate folderNodesStore
 */
function onModifySelected() {
    curriculumStore.openCurriculumSelectorModal(
        props.unit.folderId,
        (component.value as CurriculumOverviewComponent).componentId,
    );
}
</script>

<template>
    <fn-actions v-if="component !== null && folderNodesStore().hasLoaded" @modify-selected="onModifySelected" />
</template>

<style scoped></style>
