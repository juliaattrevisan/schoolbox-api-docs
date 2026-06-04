<script setup lang="ts">
/**
 * To display a curriculum overview component within the unit planner
 */

import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import FnList from "@schoolboxCurriculum/vue/folderNodes/fn-list.vue";
import FnDeleteNode from "@schoolboxCurriculum/vue/folderNodes/fn-delete-node.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import type { CurriculumOverviewComponent, Unit } from "./unitPlannerTypes";

interface Props {
    selectedNodes: Node[]; // The selected nodes
    unit: Unit; // the unit linked to the curriculum cell
    nodes: Node[]; // The nodes to display
    folderId: number; // The folder id to display the nodes for
    component: CurriculumOverviewComponent; // The component instance id to display the nodes on
}
const props = defineProps<Props>();

const emit = defineEmits<{
    onHasFilteredNodeChange: [value: boolean];
}>();

const unitPlannerStore = useUnitPlannerStore(); // to allow us to reflect our changes in back into unit planner store
const curriculumStore = useUnitPlannerCurriculumStore();
const folderNodesStore = useFolderNodesStore(props.folderId, props.component.componentId);

/**
 * To open the curriculum details modal when a node code is selected
 */
window.Vue.watch(
    () => folderNodesStore().selectedDetailsNodeId,
    async (selectedDetailsNodeId) => {
        if (selectedDetailsNodeId === UNSET_ID) {
            return;
        }
        curriculumStore.openDetailsModal(props.unit.folderId, selectedDetailsNodeId);
        await window.Vue.nextTick();
        folderNodesStore().selectedDetailsNodeId = UNSET_ID; // reset the selected details node id in the folder nodes store, so that if we click on the same node again it will open up the details modal again
    },
);

/**
 * To keep the unit planner's selected nodes in sync with the folder selected nodes
 * when the folder nodes are updated
 */
window.Vue.watch(
    () => folderNodesStore().selectedNodes,
    () => {
        const storeUnit = unitPlannerStore.units.find((unit) => unit.folderId === props.folderId);
        if (storeUnit) {
            storeUnit.curriculum.selectedNodes = folderNodesStore().selectedNodes;
        }
    },
    { deep: true },
);

/**
 * To keep the unit planner's nodes in sync with the folder nodes
 * when the folder nodes are updated
 */
window.Vue.watch(
    () => folderNodesStore().nodes,
    () => {
        const storeUnit = unitPlannerStore.units.find((unit) => unit.folderId === props.folderId);
        if (storeUnit) {
            storeUnit.curriculum.nodes = folderNodesStore().nodes;
        }
    },
    { deep: true },
);

/**
 * To keep the unit planner's empty state in sync with when this component has any filtered nodes
 */
window.Vue.watch(
    () => folderNodesStore().filteredNodes,
    () => {
        emit("onHasFilteredNodeChange", folderNodesStore().hasFilteredNodes);
    },
    { deep: true },
);

/**
 * To ensure the entire node tree can be displayed within the unit cell
 */
window.Vue.onMounted(() => {
    if (!folderNodesStore().hasLoaded) {
        folderNodesStore().init(
            props.selectedNodes,
            props.nodes,
            props.component?.filter ?? null,
            props.unit.curriculum._routes.modifyCurriculum === null,
        );
    }

    // Let the parent know if we have any filtered nodes or not
    emit("onHasFilteredNodeChange", folderNodesStore().hasFilteredNodes);
});
</script>

<template>
    <div v-if="!folderNodesStore().hasLoaded" class="loader"></div>
    <fn-list v-else :folder-id="props.folderId" :component-instance-id="props.component.componentId" :is-small="true" />
    <fn-delete-node :folder-id="props.folderId" :component-instance-id="props.component.componentId" />
</template>

<style scoped></style>
