<script setup lang="ts">
/**
 * To add new units to the course
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useAddUnitsStore, MAX_UNITS } from "./store/unitPlannerAddUnitsStore";

const unitPlannerStore = useUnitPlannerStore();
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

/**
 * Check for the existence of ?isNewCourse=1 query param and
 * if found show the add units modal automatically
 */
window.Vue.onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("isNewCourse") === "1") {
        urlParams.delete("isNewCourse");
        window.history.replaceState(
            {},
            "",
            `${window.location.pathname}${urlParams.toString().length > 0 ? `?${urlParams.toString()}` : ""}`,
        ); // remove the param from the URL
        // only show the prompt if there are no units yet
        // this is to avoid the case when isNewCourse is within a getReturnUrl already
        if (unitPlannerStore.unitCount === 0) {
            addUnitsStore.prompt();
        }
    }
});
</script>

<template>
    <basic-modal v-model:is-open="addUnitsStore.isOpen">
        <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onAddSelected">
            <h2 data-test="up-add-units-title">Create {{ locale.unit.translate(true, true) }}</h2>
            <div class="sbx-vert-scroll-container l-flex-col l-gap-s">
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
                                class="button button--icon-only f-no-margins"
                                type="button"
                                :disabled="addUnitsStore.newUnits.length === 1"
                                title="Delete"
                                aria-label="Delete"
                                :data-test="`up-add-units-delete-${index}`"
                                @click.prevent="addUnitsStore.remove(index)"
                            >
                                <sbx-icon name="delete"></sbx-icon>
                            </button>
                        </div>
                        <label v-if="addUnitsStore.validationErrors[index]" class="error"
                            >Please add a {{ locale.unit.translate(false, false) }} name</label
                        >
                    </div>
                </div>
                <div>
                    <button
                        v-if="addUnitsStore.newUnits.length < MAX_UNITS"
                        class="button sbx-button--has-icon f-no-margins"
                        data-test="up-add-units-add"
                        type="button"
                        @click.prevent="addUnitsStore.add()"
                    >
                        <sbx-icon name="add"></sbx-icon>
                        Add another
                    </button>
                </div>
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
