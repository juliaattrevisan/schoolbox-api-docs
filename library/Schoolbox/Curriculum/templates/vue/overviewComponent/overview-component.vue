<script setup lang="ts">
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 */

import { watchIgnorable } from "@vueuse/core";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useModalStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csModalStore";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import FolderNodes from "@schoolboxCurriculum/vue/folderNodes/folder-nodes.vue";
import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnActions from "@schoolboxCurriculum/vue/folderNodes/fn-actions.vue";
import Toast from "@schoolboxVue/components/toast.vue";
import OcEmptyState from "./oc-empty-state.vue";

interface Props {
    folderId: number; // The folder id to display the nodes for
    nodes: Node[]; // The curriculum nodes trees to display
    selectedNodes: Node[]; // The nodes already previously selected
    filter: Node; // The node filter to apply to the nodes, only displaying nodes that are children of this node
    dataEndpoint: string; // The endpoint to retrieve all the curriculum data from
    isReadOnly: boolean; // Can only view the currently selected nodes, not edit them
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
}
const props = defineProps<Props>();

const store = useStore(props.folderId);
const selectorStore = useSelectorStore(); // for the curriculum selector modal
const selectedNodesStore = useSelectedNodesStore(); // to update the selectedNodes linked to the curriculum selector modal
const modalStore = useModalStore(); // to open the modal

/**
 * Changes have been made to the selected nodes via the modal,
 * so update the folder store
 */
const { ignoreUpdates } = watchIgnorable(
    () => selectedNodesStore.nodes,
    () => {
        void store().updateNodes(selectedNodesStore.nodes);
    },
);

/**
 * Store the nodes and start loading the initial curriculum data
 * that's displayed within the curriculum selector modal
 */
window.Vue.onMounted(() => {
    // don't fire off the selectedNodesStore.nodes watch when we are setting the initial nodes
    ignoreUpdates(() => {
        store().init(props.nodes, props.selectedNodes, props.filter, props.isReadOnly);
        selectorStore.init({
            isReadOnly: props.isReadOnly,
            formValueName: "", // not required in this context
            dataEndpoint: props.dataEndpoint,
            selectedNodes: props.selectedNodes, // so the modal displays the latest selected nodes
        });
    });
});

/**
 * To open the curriculum code mapping modal,
 * ensuring the latest selected nodes are displayed
 */
function onUpdateSelected() {
    ignoreUpdates(() => {
        selectedNodesStore.update(store().selectedNodes); // update to latest selected nodes prior to opening the modal
    });
    modalStore.open();
}
</script>

<template>
    <oc-empty-state
        v-if="!store().hasNodes"
        :is-super-user="props.isSuperUser"
        :has-tagging="props.hasTagging"
        :locale-unit="props.localeUnit"
        @map-selected="onUpdateSelected"
    />
    <folder-nodes v-else :folder-id="props.folderId" />
    <fn-actions v-if="!props.isReadOnly && store().hasNodes" @update-selected="onUpdateSelected" />
    <cs-modal v-if="selectorStore.hasLoadedOk" />
    <curriculum-details-modal :selected-node-id="store().selectedDetailsNodeId" />
    <toast ref="toastEl"></toast>
</template>
