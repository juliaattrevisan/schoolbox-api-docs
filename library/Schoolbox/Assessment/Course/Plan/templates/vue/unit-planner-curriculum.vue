<script setup lang="ts">
/**
 * To initiate both the curriculum selector and details modals,
 * ensuring the nodes within the individual unit-cells are kept up to date
 * with any changes made via the modals
 */

import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";

const store = useUnitPlannerCurriculumStore();

/**
 * Initiate the curriculum store
 */
window.Vue.onMounted(() => {
    store.init();
});
</script>

<template>
    <cs-modal v-if="store.selectorStore?.hasLoadedOk" :starting-node-id="store.startingNodeId" />
</template>

<style scoped></style>
