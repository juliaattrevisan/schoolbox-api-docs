<script setup lang="ts">
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 */

import { watchIgnorable } from "@vueuse/core";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useModalStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csModalStore";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import FnList from "@schoolboxCurriculum/vue/folderNodes/fn-list.vue";
import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnActions from "@schoolboxCurriculum/vue/folderNodes/fn-actions.vue";
import FnDeleteNode from "@schoolboxCurriculum/vue/folderNodes/fn-delete-node.vue";
import OcEmptyState from "./oc-empty-state.vue";

interface Props {
    folderId: number; // The folder id to display the nodes for
    componentInstanceId: number; // The component instance id, to ensure the store is unique
    nodes: Node[]; // The curriculum nodes trees to display
    selectedNodes: Node[]; // The nodes already previously selected
    filter: Node | null; // to only display nodes that are children of this node
    dataEndpoint: string; // The endpoint to retrieve all the curriculum data from
    isReadOnly: boolean; // Can only view the currently selected nodes, not edit them
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
}
const props = defineProps<Props>();

const store = useStore(props.folderId, props.componentInstanceId);
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
    // don't fire off the selectedNodesStore.nodes watch when we are setting the initial nodes, otherwise we'll end up in a infinite loop!
    ignoreUpdates(() => {
        store().init(props.selectedNodes, props.nodes, props.filter, props.isReadOnly);
        selectorStore.init({
            isReadOnly: props.isReadOnly,
            formValueName: "", // not required in this context
            dataEndpoint: props.dataEndpoint,
            selectedNodes: props.selectedNodes, // so the modal displays the latest selected nodes
            includeChildren: true, // include all children in selections
        });
    });
});

/**
 * To open the curriculum code mapping modal,
 * ensuring the latest selected nodes are displayed
 */
function onModifySelected(): void {
    ignoreUpdates(() => {
        selectedNodesStore.update(store().selectedNodes); // update to latest selected nodes prior to opening the modal
    });
    modalStore.open();
}

/**
 * The modal has been closed, so reset the selected details node id
 * to allow it to be opened again on the same node if required
 */
function onModalClosed(): void {
    store().selectedDetailsNodeId = UNSET_ID;
}
</script>

<template>
    <oc-empty-state
        v-if="!store().hasNodes || store().hasHiddenNodes"
        :is-super-user="props.isSuperUser"
        :has-tagging="props.hasTagging"
        :locale-unit="props.localeUnit"
        :folder-id="props.folderId"
        :component-instance-id="props.componentInstanceId"
        @map-selected="onModifySelected"
    />
    <fn-list v-else :folder-id="props.folderId" :component-instance-id="props.componentInstanceId" />
    <fn-actions
        v-if="!props.isReadOnly && store().hasNodes"
        :folder-id="props.folderId"
        :component-id="props.componentInstanceId"
        @modify-selected="onModifySelected"
    />
    <fn-delete-node :folder-id="props.folderId" :component-instance-id="props.componentInstanceId" />
    <cs-modal v-if="selectorStore.hasLoadedOk" :starting-node-id="props.filter?.id" />
    <curriculum-details-modal :selected-node-id="store().selectedDetailsNodeId" @closed="onModalClosed" />
</template>
