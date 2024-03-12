<script setup lang="ts">
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import { useStore } from "./store/overviewComponentStore";
import OcEmptyState from "./oc-empty-state.vue";
import OcViewNode from "./oc-view-node.vue";

interface Props {
    nodeTrees: Node[]; // The curriculum nodes trees to display
    dataEndpoint: string; // The endpoint to retrieve all the curriculum data from
    selectedNodes: Node[]; // The nodes already previously selected
    isReadOnly: boolean; // Can only view the currently selected nodes, not edit them
    formValueName: string; // the input to update with the selected nodes
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
}
const props = defineProps<Props>();

const store = useStore();
const selectorStore = useSelectorStore();
const depth = window.Vue.ref(0); // to increment each time we go down the node tree to visually indicate children

/**
 * Start loading the curriculum data
 */
window.Vue.onMounted(() => {
    store.init(props.nodeTrees, props.selectedNodes);
    selectorStore.init(props);
});
</script>

<template>
    <oc-empty-state
        v-if="store.hasNoNodes"
        :is-super-user="props.isSuperUser"
        :has-tagging="props.hasTagging"
        :locale-unit="props.localeUnit"
    />
    <div v-else class="l-flex-col">
        <oc-view-node v-for="node in store.nodeTrees" :key="node.id" :node="node" :depth="depth" />
    </div>
    <cs-modal v-if="selectorStore.hasLoadedOk" />
    <curriculum-details-modal :selected-node-id="store.selectedDetailsNodeId" />
</template>
