<script setup lang="ts">
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import FolderNodes from "@schoolboxCurriculum/vue/folderNodes/folder-nodes.vue";
import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnActions from "@schoolboxCurriculum/vue/folderNodes/fn-actions.vue";
import OcEmptyState from "./oc-empty-state.vue";

interface Props {
    nodes: Node[]; // The curriculum nodes trees to display
    folderId: number; // The folder id to display the nodes for
    dataEndpoint: string; // The endpoint to retrieve all the curriculum data from
    selectedNodes: Node[]; // The nodes already previously selected
    isReadOnly: boolean; // Can only view the currently selected nodes, not edit them
    formValueName: string; // the input to update with the selected nodes
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
}
const props = defineProps<Props>();

const store = useStore(props.folderId);
const selectorStore = useSelectorStore();

/**
 * Store the nodes and start loading the initial curriculum data
 * that's displayed within the curriculum selector modal
 */
window.Vue.onMounted(() => {
    store().init(props.nodes, props.selectedNodes);
    selectorStore.init(props);
});
</script>

<template>
    <oc-empty-state
        v-if="store().hasNoNodes"
        :is-super-user="props.isSuperUser"
        :has-tagging="props.hasTagging"
        :locale-unit="props.localeUnit"
    />
    <folder-nodes v-else :folder-id="props.folderId" />
    <fn-actions v-if="!props.isReadOnly" />
    <cs-modal v-if="selectorStore.hasLoadedOk" />
    <curriculum-details-modal :selected-node-id="store().selectedDetailsNodeId" />
</template>
