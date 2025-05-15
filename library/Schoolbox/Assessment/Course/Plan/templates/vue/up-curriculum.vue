<script setup lang="ts">
/**
 * To initiate both the curriculum selector and details modal
 */

import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";

interface Props {
    folderId: number; // the folder id to use when loading current curriculum
}
const props = defineProps<Props>();

const store = useUnitPlannerCurriculumStore();

/**
 * Initiate the curriculum store
 */
window.Vue.onMounted(() => {
    store.init(props.folderId);
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
        :has-tabs="store.unitNodesStore.hasNodes"
    />
    <curriculum-details-modal :selected-node-id="store.selectedDetailsNodeId" @closed="onDetailsModalClosed" />
</template>

<style scoped></style>
