<script setup lang="ts">
/**
 * To add new units to the course
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useAddUnitsStore } from "./store/unitPlannerAddUnitsStore";

const addUnitsStore = useAddUnitsStore();

/**
 * To update the button text as the component state changes.
 * @returns {string}
 */
const addButtonText = window.Vue.computed(() => {
    if (addUnitsStore.isProcessing) {
        return "Creating...";
    }
    return "Create unit";
});

/**
 * to close the add unit view
 */
function onCancelSelected(): void {
    addUnitsStore.cancel();
}

/**
 * to add a new unit
 */
function onAddSelected(): void {
    if (addUnitsStore.isProcessing) {
        return;
    }
    void addUnitsStore.add();
}
</script>

<template>
    <basic-modal v-model:is-open="addUnitsStore.isOpen">
        <h2>Create new unit</h2>
    </basic-modal>
</template>
