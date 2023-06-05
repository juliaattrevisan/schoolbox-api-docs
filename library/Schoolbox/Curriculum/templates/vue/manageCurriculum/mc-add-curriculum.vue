<script setup lang="ts">
/**
 * to show further info in a modal on a code that's been selected
 */

import { computed, ref, watch } from "vue";
import { getButtonTextDisabledDueToStorageLimitSuffix } from "@js/editor/settings/wordCountSettings";
import { useStore } from "./store/manageCurriculumStore";
import { useAddCurriculumStore } from "./store/mcAddCurriculumStore";
import { useDescriptionEditor } from "./composable/useDescriptionEditor";

const store = useStore();
const addCurriculumStore = useAddCurriculumStore();

/**
 * Store a reference to the ckeditor used for the description
 */
const ckeditorIdAttr = `new-curriculum`;

const isMaxCharCountExceeded = ref<boolean>(false);

/**
 * To update the button text as the component state changes.
 *
 * @returns { string }
 */
const addButtonText = computed(() => {
    if (addCurriculumStore.isProcessing) {
        return "Adding...";
    }

    return "Add" + (isMaxCharCountExceeded.value ? getButtonTextDisabledDueToStorageLimitSuffix() : "");
});

/**
 * Only enable the button once the conditions are met
 *
 * @returns { boolean }
 */
const isAddDisabled = computed(() => {
    return (
        isMaxCharCountExceeded.value ||
        addCurriculumStore.curriculum.name?.trim().length === 0 ||
        addCurriculumStore.isProcessing
    );
});

/**
 * Use a customised ckeditor for the description field
 */
const descriptionFieldValue = useDescriptionEditor(
    addCurriculumStore.curriculum?.description as string,
    ckeditorIdAttr,
    isMaxCharCountExceeded
);

/**
 * And watch for changes on this field, so we can update the description in the store
 */
watch(descriptionFieldValue, (newValue: string) => {
    addCurriculumStore.curriculum.description = newValue;
});

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
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <form>
                <section>
                    <fieldset class="content">
                        <legend>Add new Curriculum</legend>
                        <div class="small-12 columns">
                            <label for="name" class="required">Name</label>
                            <input
                                id="name"
                                maxlength="2048"
                                required
                                type="text"
                                v-model="addCurriculumStore.curriculum.name"
                            />
                        </div>
                        <div class="small-12 columns">
                            <label for="code">Code</label>
                            <input type="text" id="code" v-model="addCurriculumStore.curriculum.code" maxlength="64" />
                        </div>
                        <div class="small-12 columns">
                            <label for="description">Description</label>
                            <textarea :id="ckeditorIdAttr" rows="6"></textarea>
                        </div>
                        <div class="small-12 columns">
                            <ul class="flex-list">
                                <li>
                                    <button
                                        :disabled="addCurriculumStore.isProcessing"
                                        @click.prevent="onCancelSelected"
                                        class="button icon-close"
                                    >
                                        Cancel
                                    </button>
                                </li>
                                <li>
                                    <button
                                        :data-submit-button-for="ckeditorIdAttr"
                                        :disabled="isAddDisabled"
                                        @click.prevent="onAddSubsectionSelected"
                                        class="button icon-add submit"
                                        data-test-add-curriculum
                                        ref="addButton"
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
