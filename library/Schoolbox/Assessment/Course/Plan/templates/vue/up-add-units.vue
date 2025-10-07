<script setup lang="ts">
/**
 * To add new units to the course
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useAddUnitsStore } from "./store/unitPlannerAddUnitsStore";

const addUnitsStore = useAddUnitsStore();

/**
 * to add a new unit
 */
function onAddSelected(): void {
    if (addUnitsStore.isProcessing) {
        return;
    }
    void addUnitsStore.create();
}
</script>

<template>
    <basic-modal v-model:is-open="addUnitsStore.isOpen">
        <div class="l-flex-col l-gap-s f-no-margins">
            <h2>Create new {{ locale.unit.translate(true, false) }}</h2>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" @click.prevent="addUnitsStore.cancel()">Cancel</button>
                <button class="button submit l-flex__item--1" @click.prevent="onAddSelected">
                    {{ addUnitsStore.isProcessing ? "Creating..." : "Create" }}
                </button>
            </div>
        </div>
    </basic-modal>
</template>
