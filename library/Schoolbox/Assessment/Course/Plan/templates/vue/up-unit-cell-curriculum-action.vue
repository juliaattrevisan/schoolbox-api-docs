<script setup lang="ts">
/**
 * To display a curriculum overview component within the unit planner
 */

// import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnActions from "@schoolboxCurriculum/vue/folderNodes/fn-actions.vue";
// import type { Node } from "@schoolboxCurriculum/types/Node";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import type { CurriculumOverviewComponent } from "./unitPlannerTypes";

interface Props {
    folderId: number; // The folder id to display the nodes for
    component: CurriculumOverviewComponent; // The component instance id to display the nodes on
}
const props = defineProps<Props>();

const curriculumStore = useUnitPlannerCurriculumStore();
// const hasLoaded = window.Vue.ref(false);

/**
 * To open the curriculum code mapping modal,
 * ensuring the relevant selected nodes are displayed by
 * passing in the folderId and componentId to target the appropriate folderNodesStore
 */
function onModifySelected() {
    curriculumStore.openCurriculumSelectorModal(props.folderId, props.component.componentId);
}
</script>

<template>
    <fn-actions @modify-selected="onModifySelected" />
</template>

<style scoped></style>
