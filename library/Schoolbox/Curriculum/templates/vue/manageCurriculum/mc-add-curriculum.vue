<script setup lang="ts">
/**
 * to show further info in a modal on a code that's been selected
 */

import { useStore } from "./store/manageCurriculumStore";
import { useAddCurriculumStore } from "./store/mcAddCurriculumStore";
import { useDescriptionEditor } from "./composable/useDescriptionEditor";

const store = useStore();
const addCurriculumStore = useAddCurriculumStore();

/**
 * Store a reference to the ckeditor used for the description
 *
 * @returns { String }
 */
const ckeditorInstance = window.Vue.computed(() => `new-curriculum`);

/**
 * Use a customised ckeditor for the description field
 */
const descriptionField = useDescriptionEditor(
    addCurriculumStore.curriculum?.description as string,
    ckeditorInstance.value
);

/**
 * And watch for changes on this field so we can update the description in the store
 */
window.Vue.watch(descriptionField, (newValue: string) => {
    addCurriculumStore.curriculum.description = newValue;
});

/**
 * To instantly notify the user that the subsection is being added
 * by updating the button text
 *
 * @returns { String }
 */
const addButtonText = window.Vue.computed(() => (addCurriculumStore.isProcessing ? "Adding..." : "Add"));

/**
 * submit save
 */
function onAddSubsectionSelected() {
    addCurriculumStore.add();
}

/**
 * to display the toast on curriculum added / failed
 */
addCurriculumStore.$onAction(({ name, after, onError }) => {
    if (name === "add") {
        store.toastEl.value.activate("Adding...");
        after((result) => {
            store.toastEl.value.timeout(result.message, true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.value.timeout("Unable to add Curriculum", false, 5000);
        });
    }
});

/**
 * close the addSubsection view
 */
function onCancelSelected() {
    addCurriculumStore.cancel();
}

/**
 * Only enable the button once the conditions are met
 *
 * @returns { Boolean }
 */
const isAddDisabled = window.Vue.computed(() => {
    let addButton = document.querySelector("button.submit[data-submit-button-for=" + ckeditorInstance.value + "]");

    // If the button text contains 'disabled', then the button must remain disabled.
    // The 'disabled' text is added by wordCountSettings.ts in response to the content storage limit being exceeded.
    if (addButton !== null && addButton.innerHTML.includes("disabled")) {
        return true;
    }

    if (typeof addCurriculumStore.curriculum.name === "string") {
        return addCurriculumStore.curriculum?.name.trim().length === 0 || addCurriculumStore.isProcessing;
    }
    return false;
});
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content">
                    <legend>Add new Curriculum</legend>
                    <div class="small-12 columns">
                        <label for="name" class="required">Name</label>
                        <input
                            type="text"
                            id="name"
                            v-model="addCurriculumStore.curriculum.name"
                            maxlength="2048"
                            required
                        />
                    </div>
                    <div class="small-12 columns">
                        <label for="code">Code</label>
                        <input type="text" id="code" v-model="addCurriculumStore.curriculum.code" maxlength="64" />
                    </div>
                    <div class="small-12 columns">
                        <label for="description">Description</label>
                        <textarea :id="ckeditorInstance" rows="6"></textarea>
                    </div>
                    <div class="small-12 columns">
                        <ul class="flex-list">
                            <li>
                                <button
                                    class="button icon-close"
                                    :disabled="addCurriculumStore.isProcessing"
                                    @click.prevent="onCancelSelected"
                                >
                                    Cancel
                                </button>
                            </li>
                            <li>
                                <button
                                    class="button icon-add submit"
                                    :data-submit-button-for="ckeditorInstance"
                                    data-test-add-curriculum
                                    :disabled="isAddDisabled"
                                    @click.prevent="onAddSubsectionSelected"
                                >
                                    {{ addButtonText }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </fieldset>
            </section>
        </div>
    </div>
</template>

<style lang="scss" scoped>
legend {
    font-size: 1rem;
    margin-bottom: 1rem;
}
</style>
