<script setup lang="ts">
/**
 * To add a new unit
 */

import { useAddUnitStore } from "./store/unitPlannerAddUnitStore";

const addUnitStore = useAddUnitStore();
const hasNoName = window.Vue.ref(false);

/**
 * To update the button text as the component state changes.
 * @returns {string}
 */
const addButtonText = window.Vue.computed(() => {
    if (addUnitStore.isProcessing) {
        return "Creating...";
    }
    return "Create unit";
});

/**
 * to close the add unit view
 */
function onCancelSelected(): void {
    addUnitStore.cancel();
    hasNoName.value = false;
}

/**
 * to add a new unit
 */
function onAddSelected(): void {
    if (addUnitStore.isProcessing) {
        return;
    }
    if (addUnitStore.name === "") {
        hasNoName.value = true;
    } else {
        void addUnitStore.add();
        hasNoName.value = false;
    }
}
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <form>
                <section>
                    <fieldset class="content">
                        <legend class="l-mar-m--bottom t-fs-m">Add new unit</legend>
                        <div class="small-12 columns l-mar-m--bottom">
                            <label for="name" class="required">Name</label>
                            <input
                                id="name"
                                v-model="addUnitStore.name"
                                class="f-no-margins"
                                maxlength="2048"
                                required
                                type="text"
                            />
                            <label v-if="hasNoName" class="error f-no-margins">Please enter a name for the unit</label>
                        </div>
                        <div class="small-12 columns">
                            <ul class="flex-list">
                                <li>
                                    <button
                                        type="button"
                                        class="button icon-close"
                                        :disabled="addUnitStore.isProcessing"
                                        @click.prevent="onCancelSelected"
                                    >
                                        Cancel
                                    </button>
                                </li>
                                <li>
                                    <button
                                        type="submit"
                                        class="button icon-add submit"
                                        data-test-add-unit
                                        @click.prevent="onAddSelected"
                                    >
                                        {{ addButtonText }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </fieldset>
                </section>
            </form>
        </div>
    </div>
</template>
