<script setup lang="ts">
/**
 * To initiate both the curriculum selector and details modal
 */

import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";

interface Props {
    parentFolderId: number; // the parent folder id to use when loading the current curriculum tab
}
const props = defineProps<Props>();

const store = useUnitPlannerCurriculumStore();

/**
 * Initiate the curriculum store
 */
window.Vue.onMounted(() => {
    store.init(props.parentFolderId);
});

/**
 * The modal has been closed, so reset the selected details node id
 * to allow it to be opened again on the same node if required
 */
function onDetailsModalClosed(): void {
    store.selectedDetailsNodeId = UNSET_ID;
}
</script>

<template>
    <cs-modal
        v-if="store.selectorStore?.hasLoadedOk"
        :starting-node-id="store.startingNodeId"
        :has-tabs="store.currentNodesStore.hasNodes"
    />
    <curriculum-details-modal :selected-node-id="store.selectedDetailsNodeId" @closed="onDetailsModalClosed" />
</template>

<style scoped></style>
