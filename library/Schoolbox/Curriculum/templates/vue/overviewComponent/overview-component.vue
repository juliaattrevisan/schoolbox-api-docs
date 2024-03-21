<script setup lang="ts">
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useModalStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csModalStore";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import FolderNodes from "@schoolboxCurriculum/vue/folderNodes/folder-nodes.vue";
import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnActions from "@schoolboxCurriculum/vue/folderNodes/fn-actions.vue";
import OcEmptyState from "./oc-empty-state.vue";

interface Props {
    folderId: number; // The folder id to display the nodes for
    nodes: Node[]; // The curriculum nodes trees to display
    selectedNodes: Node[]; // The nodes already previously selected
    dataEndpoint: string; // The endpoint to retrieve all the curriculum data from
    isReadOnly: boolean; // Can only view the currently selected nodes, not edit them
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
}
const props = defineProps<Props>();

const store = useStore(props.folderId);
const selectorStore = useSelectorStore(); // The store for the curriculum selector modal
const selectedNodesStore = useSelectedNodesStore(); // to update the selectedNodes linked to the curriculum selector modal
const modalStore = useModalStore(); // to open the modal

/**
 * Store the nodes and start loading the initial curriculum data
 * that's displayed within the curriculum selector modal
 */
window.Vue.onMounted(() => {
    store().init(props.nodes, props.selectedNodes, props.isReadOnly);
    selectorStore.init({
        isReadOnly: props.isReadOnly,
        formValueName: "", // not required in this context
        dataEndpoint: props.dataEndpoint,
        selectedNodes: props.selectedNodes, // so the modal displays the latest selected nodes
    });
});

/**
 * To open the curriculum code mapping modal,
 * ensuring the latest selected nodes are displayed
 */
function onUpdateSelected() {
    selectedNodesStore.update(store().selectedNodes); // update to latest selected nodes prior to opening the modal
    modalStore.open();
}

/**
 * Changes have been made to the selected nodes via the modal,
 * so update the folder store
 */
window.Vue.watch(
    () => selectedNodesStore.nodes,
    () => {
        void store().updateNodes(selectedNodesStore.nodes);
    },
);
</script>

<template>
    <oc-empty-state
        v-if="store().hasNoNodes"
        :is-super-user="props.isSuperUser"
        :has-tagging="props.hasTagging"
        :locale-unit="props.localeUnit"
        @map-selected="onUpdateSelected"
    />
    <folder-nodes v-else :folder-id="props.folderId" />
    <fn-actions v-if="!props.isReadOnly" @update-selected="onUpdateSelected" />
    <cs-modal v-if="selectorStore.hasLoadedOk" />
    <curriculum-details-modal :selected-node-id="store().selectedDetailsNodeId" />
</template>
