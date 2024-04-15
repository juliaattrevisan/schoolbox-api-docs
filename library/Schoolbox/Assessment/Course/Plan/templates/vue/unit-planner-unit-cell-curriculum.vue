<script setup lang="ts">
/**
 * To display a curriculum overview component within the unit planner
 */

import { watchIgnorable } from "@vueuse/core";
import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import FnList from "@schoolboxCurriculum/vue/folderNodes/fn-list.vue";
import FnDeleteNode from "@schoolboxCurriculum/vue/folderNodes/fn-delete-node.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import type { CurriculumOverviewComponent } from "./unitPlannerTypes";

interface Props {
    nodes: Node[]; // The nodes to display
    selectedNodes: Node[]; // The selected nodes
    folderId: number; // The folder id to display the nodes for
    component: CurriculumOverviewComponent; // The component instance id to display the nodes on
}
const props = defineProps<Props>();

const emit = defineEmits(["updateContentHeight", "loaded"]);

const curriculumStore = useUnitPlannerCurriculumStore();
const folderNodesStore = useFolderNodesStore(props.folderId, props.component.componentId);
const hasLoaded = window.Vue.ref(false);

/**
 * To open the curriculum details modal when a node code is selected
 */
window.Vue.watch(
    () => folderNodesStore().selectedDetailsNodeId,
    () => {
        curriculumStore.selectedDetailsNodeId = folderNodesStore().selectedDetailsNodeId;
    },
);

/**
 * So we can unset the selected details node id
 * so the same node can be opened again if desired
 */
const { ignoreUpdates } = watchIgnorable(
    () => curriculumStore.selectedDetailsNodeId,
    () => {
        if (curriculumStore.selectedDetailsNodeId !== UNSET_ID) {
            ignoreUpdates(() => {
                folderNodesStore().selectedDetailsNodeId = UNSET_ID;
            });
        }
    },
);

/**
 * To ensure the entire node tree can be displayed within the unit cell
 */
window.Vue.onMounted(async () => {
    await folderNodesStore().init(props.selectedNodes, props.nodes, props.component?.filter ?? null, false);
    emit("loaded"); // to update the height of the unit planner cell
    hasLoaded.value = true;
    await window.Vue.nextTick();
    emit("updateContentHeight"); // to update the height of the unit planner cell
});

/**
 * To update the content height when the nodes have changed
 */
window.Vue.watch(
    () => folderNodesStore().filteredNodes,
    async () => {
        await window.Vue.nextTick();
        emit("updateContentHeight");
    },
);
</script>

<template>
    <div v-if="!hasLoaded" class="loader"></div>
    <fn-list v-else :folder-id="props.folderId" :component-instance-id="props.component.componentId" :is-small="true" />
    <fn-delete-node :folder-id="props.folderId" :component-instance-id="props.component.componentId" />
</template>

<style scoped></style>
