<script setup lang="ts">
/**
 * To initiate both the curriculum selector and details modals,
 * ensuring the nodes within the individual unit-cells are kept up to date
 * with any changes made via the modals
 */

import { watchIgnorable } from "@vueuse/core";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import { useModalStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csModalStore";

const selectorStore = useSelectorStore(); // for the curriculum selector modal
const selectedNodesStore = useSelectedNodesStore(); // to update the selectedNodes linked to the curriculum selector modal
const modalStore = useModalStore(); // to open the modal
const currentFolderId = window.Vue.ref<number | null>(null); // to target the appropriate folderNodesStore.selectedNodes when updating via the curriculum modal
const currentComponentId = window.Vue.ref<number | null>(null); // to target the appropriate folderNodesStore.selectedNodes when updating via the curriculum modal
const startingNodeId = window.Vue.ref<number | undefined>(undefined);

/**
 * Changes have been made to the selected nodes via the modal,
 * so update the folder store
 */
const { ignoreUpdates } = watchIgnorable(
    () => selectedNodesStore.nodes,
    () => {
        // target the correct store to update the nodes
        const folderNodesStore = useFolderNodesStore(currentFolderId.value!, currentComponentId.value!);
        void folderNodesStore().updateNodes(selectedNodesStore.nodes);
    },
);

/**
 * To open the curriculum code mapping modal,
 * ensuring the latest selected nodes are displayed
 * @param {number} folderId - the folder id to open the modal on
 * @param {number} componentId - the component id to open the modal on
 * @param {Node[]} selectedNodes - The selected nodes
 */
function onModifyCurriculumSelected(folderId: number, componentId: number, selectedNodes: Node[]) {
    // dispatch to the top parent to open up the curriculum modal
    currentFolderId.value = folderId;
    currentComponentId.value = componentId;
    ignoreUpdates(() => {
        selectedNodesStore.update(selectedNodes); // update to latest selected nodes prior to opening the modal
    });
    const folderNodesStore = useFolderNodesStore(folderId, componentId);
    startingNodeId.value = folderNodesStore().filter ? folderNodesStore().filter?.id : undefined;
    modalStore.open();
}

/**
 * Initiate the curriculum selector store
 */
window.Vue.onMounted(() => {
    selectorStore.init({
        isReadOnly: false,
        formValueName: "", // not required in this context
        dataEndpoint: "/api/curriculum",
        selectedNodes: [], // so the modal displays the latest selected nodes
    });
});
</script>

<template>
    <cs-modal v-if="selectorStore.hasLoadedOk" :starting-node-id="startingNodeId" />
</template>

<style scoped></style>
