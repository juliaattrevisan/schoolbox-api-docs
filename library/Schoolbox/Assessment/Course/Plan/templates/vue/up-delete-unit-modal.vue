<script setup lang="ts">
/**
 * To delete a unit from the course
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import { useDeleteUnitsStore } from "./store/unitPlannerDeleteUnitStore";

const deleteUnitsStore = useDeleteUnitsStore();

/**
 * To delete a unit
 */
function onDeleteSelected(): void {
    if (deleteUnitsStore.isProcessing) {
        return;
    }
    void deleteUnitsStore.deleteUnit();
}
</script>

<template>
    <basic-modal v-model:is-open="deleteUnitsStore.isOpen" size="s">
        <form class="u-flex-col u-gap-s f-no-margins" @submit.prevent="onDeleteSelected">
            <h2 data-test="up-delete-units-title">Please Confirm</h2>
            <p>
                Are you sure you wish to delete this {{ locale.unit.translate(false, false) }}? This will also delete
                all {{ locale.activity.translate(true, false) }} in the {{ locale.unit.translate(false, false) }}.
            </p>
            <request-error v-if="deleteUnitsStore.loadError" :further-info="deleteUnitsStore.loadError" />
            <div class="u-flex u-gap-xs f-no-margins">
                <button
                    class="button submit u-flex__item--1"
                    type="button"
                    data-test="up-delete-unit-delete"
                    @click.prevent="onDeleteSelected"
                >
                    {{ deleteUnitsStore.isProcessing ? "Deleting..." : "Yes" }}
                </button>
                <button
                    class="button u-flex__item--1"
                    data-test="up-delete-units-cancel"
                    @click.prevent="deleteUnitsStore.cancel()"
                >
                    Cancel
                </button>
            </div>
        </form>
    </basic-modal>
</template>
