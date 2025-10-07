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
            <div>
                <!-- Loop through newUnits and display label and text input for each -->
                <div v-for="(unit, index) in addUnitsStore.newUnits" :key="index" class="l-flex-col l-gap-xs">
                    <label :for="`unit-${index}`">Name:</label>
                    <div class="l-flex l-gap-xs l-align-items-center">
                        <input
                            :id="`unit-${index}`"
                            v-model="addUnitsStore.newUnits[index]"
                            type="text"
                            class="input l-flex__item--1"
                            :placeholder="`Enter ${locale.unit.translate(false, false).toLowerCase()} name`"
                        />
                        <button
                            v-if="index > 0"
                            class="button button--icon-only icon-delete f-no-margins"
                            :disabled="addUnitsStore.newUnits.length === 1"
                            title="Delete"
                            aria-label="Delete"
                            data-test="up-add-units-delete"
                            @click.prevent="addUnitsStore.remove(index)"
                        ></button>
                    </div>
                </div>
                <button
                    class="button icon-add f-no-margins"
                    data-test="up-add-units-add"
                    @click.prevent="addUnitsStore.add()"
                >
                    Add another
                </button>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button
                    class="button l-flex__item--1"
                    data-test="up-add-units-cancel"
                    @click.prevent="addUnitsStore.cancel()"
                >
                    Cancel
                </button>
                <button
                    class="button submit l-flex__item--1"
                    data-test="up-add-units-create"
                    @click.prevent="onAddSelected"
                >
                    {{ addUnitsStore.isProcessing ? "Creating..." : "Create" }}
                </button>
            </div>
        </div>
    </basic-modal>
</template>
