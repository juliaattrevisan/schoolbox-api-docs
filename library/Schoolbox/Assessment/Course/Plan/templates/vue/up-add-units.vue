<script setup lang="ts">
/**
 * To add new units to the course
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import { useAddUnitsStore } from "./store/unitPlannerAddUnitsStore";

const addUnitsStore = useAddUnitsStore();

/**
 * To add new units
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
        <form class="l-flex-col l-gap-s f-no-margins" @submit.prevent="onAddSelected">
            <h2 data-test="up-add-units-title">Create new {{ locale.unit.translate(true, false) }}</h2>
            <div class="l-flex-col l-gap-s">
                <!-- Loop through newUnits and display label and text input for each -->
                <div v-for="(unit, index) in addUnitsStore.newUnits" :key="index" class="l-flex-col l-gap-xs">
                    <label :for="`unit-${index}`" class="required">Name</label>
                    <div class="l-flex l-gap-xs l-align-items-center f-no-margins">
                        <input
                            :id="`unit-${index}`"
                            v-model="addUnitsStore.newUnits[index]"
                            type="text"
                            class="input l-flex__item--1"
                            maxlength="255"
                            :data-test="`up-add-units-name-input-${index}`"
                            :placeholder="`Enter ${locale.unit.translate(false, false).toLowerCase()} name`"
                        />
                        <button
                            v-if="index > 0"
                            class="button button--icon-only icon-delete f-no-margins"
                            type="button"
                            :disabled="addUnitsStore.newUnits.length === 1"
                            title="Delete"
                            aria-label="Delete"
                            :data-test="`up-add-units-delete-${index}`"
                            @click.prevent="addUnitsStore.remove(index)"
                        ></button>
                    </div>
                    <label v-if="addUnitsStore.validationErrors[index]" class="error"
                        >Please add a {{ locale.unit.translate(false, false) }} name</label
                    >
                </div>
            </div>
            <div>
                <button
                    class="button icon-add f-no-margins"
                    data-test="up-add-units-add"
                    type="button"
                    @click.prevent="addUnitsStore.add()"
                >
                    Add another
                </button>
            </div>
            <request-error v-if="addUnitsStore.loadError" :further-info="addUnitsStore.loadError" />
            <div class="l-flex l-gap-xs f-no-margins">
                <button
                    class="button l-flex__item--1"
                    data-test="up-add-units-cancel"
                    type="button"
                    @click.prevent="addUnitsStore.cancel()"
                >
                    Cancel
                </button>
                <button class="button submit l-flex__item--1" data-test="up-add-units-create">
                    {{ addUnitsStore.isProcessing ? "Creating..." : "Create" }}
                </button>
            </div>
        </form>
    </basic-modal>
</template>
