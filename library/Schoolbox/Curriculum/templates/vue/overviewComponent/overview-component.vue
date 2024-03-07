<script setup lang="ts">
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import OcEmptyState from "./oc-empty-state.vue";

interface Props {
    // nodes: Node[] | null; // The curriculum nodes to display
    dataEndpoint: string; // The endpoint to retrieve all the curriculum data from
    selectedNodes: Node[]; // The nodes already previously selected
    isReadOnly: boolean; // Can only view the currently selected nodes, not edit them
    formValueName: string; // the input to update with the selected nodes
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
}
const props = defineProps<Props>();

const selectorStore = useSelectorStore();

/**
 * Start loading the curriculum data
 */
window.Vue.onMounted(() => {
    selectorStore.init(props);
});
</script>

<template>
    <oc-empty-state
        :is-super-user="props.isSuperUser"
        :has-tagging="props.hasTagging"
        :locale-unit="props.localeUnit"
    />
    <cs-modal v-if="selectorStore.hasLoadedOk" />
    <curriculum-details-modal />
</template>
