<script setup lang="ts">
/**
 * To add a new root curriculum node
 */

import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import { getButtonTextDisabledDueToStorageLimitSuffix } from "@js/editor/settings/wordCountSettings";
import { useAddCurriculumStore } from "./store/mcAddCurriculumStore";
import { useDescriptionEditor } from "./composable/useDescriptionEditor";

const addCurriculumStore = useAddCurriculumStore();

/**
 * Store a reference to the ckeditor used for the description
 */
const ckeditorIdAttr = `new-curriculum`;

const isMaxCharCountExceeded = window.Vue.ref<boolean>(false);

/**
 * To update the button text as the component state changes.
 * @returns { string }
 */
const addButtonText = window.Vue.computed(() => {
    if (addCurriculumStore.isProcessing) {
        return "Adding...";
    }

    return `Add${isMaxCharCountExceeded.value ? getButtonTextDisabledDueToStorageLimitSuffix() : ""}`;
});

/**
 * Only enable the button once the conditions are met
 * @returns { boolean }
 */
const isAddDisabled = window.Vue.computed(() => {
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
    isMaxCharCountExceeded,
);

/**
 * And watch for changes on this field, so we can update the description in the store
 */
window.Vue.watch(descriptionFieldValue, (newValue: string) => {
    addCurriculumStore.curriculum.description = newValue;
});

/**
 * submit save
 */
async function onAddSubsectionSelected(): Promise<void> {
    await addCurriculumStore.add();
}

/**
 * to display the toast on curriculum added / failed
 */
addCurriculumStore.$onAction(({ name, after, onError }) => {
    if (name === "add") {
        toast.activate("Adding...");
        after((result) => {
            toast.timeout({ text: result.message, isSuccess: true });
        });
        onError((error) => {
            console.error("Unable to add Curriculum", error);
            toast.timeout({ text: "Unable to add Curriculum", isSuccess: false });
        });
    }
});

/**
 * close the addSubsection view
 */
function onCancelSelected(): void {
    addCurriculumStore.cancel();
}
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <form>
                <section>
                    <fieldset class="content">
                        <legend class="l-mar-m--bottom t-fs-m">Add new Curriculum</legend>
                        <div class="small-12 columns">
                            <label for="name" class="required">Name</label>
                            <input
                                id="name"
                                v-model="addCurriculumStore.curriculum.name"
                                maxlength="2048"
                                required
                                type="text"
                            />
                        </div>
                        <div class="small-12 columns">
                            <label for="description">Description</label>
                            <textarea :id="ckeditorIdAttr" rows="6"></textarea>
                        </div>
                        <div class="small-12 columns">
                            <ul class="flex-list">
                                <li>
                                    <button
                                        type="button"
                                        class="button icon-close"
                                        :disabled="addCurriculumStore.isProcessing"
                                        @click.prevent="onCancelSelected"
                                    >
                                        Cancel
                                    </button>
                                </li>
                                <li>
                                    <button
                                        ref="addButton"
                                        type="submit"
                                        class="button icon-add submit"
                                        data-test="add-curriculum"
                                        :data-submit-button-for="ckeditorIdAttr"
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
            </form>
        </div>
    </div>
</template>
