<script setup lang="ts">
/**
 * To add a new unit
 */

import { useAddUnitStore } from "./store/unitPlannerAddUnitStore";

const addUnitStore = useAddUnitStore();

/**
 * To update the button text as the component state changes.
 * @returns {string}
 */
const addButtonText = window.Vue.computed(() => {
    if (addUnitStore.isProcessing) {
        return "Adding...";
    }
    return "Add Unit";
});

/**
 * to close the add unit view
 */
function onCancelSelected() {
    addUnitStore.cancel();
}

/**
 * to add a new unit
 */
function onAddSelected() {
    void addUnitStore.add();
}
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <form>
                <section>
                    <fieldset class="content">
                        <legend>Add new Unit</legend>
                        <div class="small-12 columns">
                            <label for="name" class="required">Name</label>
                            <input id="name" maxlength="2048" required type="text" />
                        </div>
                        <div class="small-12 columns">
                            <ul class="flex-list">
                                <li>
                                    <button
                                        class="button icon-close"
                                        :disabled="addUnitStore.isProcessing"
                                        @click.prevent="onCancelSelected"
                                    >
                                        Cancel
                                    </button>
                                </li>
                                <li>
                                    <button
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

<style lang="scss" scoped>
legend {
    font-size: 1rem;
    margin-bottom: 1rem;
}
</style>
