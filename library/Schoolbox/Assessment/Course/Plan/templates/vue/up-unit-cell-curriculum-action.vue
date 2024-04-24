<script setup lang="ts">
/**
 * To display the actions available for the curriculum overview component,
 * which is to modify the selected nodes via the curriculum selector modal
 */

import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnActions from "@schoolboxCurriculum/vue/folderNodes/fn-actions.vue";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import type { CurriculumOverviewComponent } from "./unitPlannerTypes";

interface Props {
    folderId: number; // The folder id to display the nodes for
    component: CurriculumOverviewComponent; // The component instance id to display the nodes on
}
const props = defineProps<Props>();

const curriculumStore = useUnitPlannerCurriculumStore();
const folderNodesStore = useFolderNodesStore(props.folderId, props.component.componentId);

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
    <fn-actions v-if="folderNodesStore().hasLoaded" @modify-selected="onModifySelected" />
</template>

<style scoped></style>
