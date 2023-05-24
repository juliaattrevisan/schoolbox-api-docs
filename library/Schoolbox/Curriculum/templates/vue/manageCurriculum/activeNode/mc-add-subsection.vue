<script setup lang="ts">
/**
 * to show further info in a modal on a code that's been selected
 */

import { useStore } from "../store/manageCurriculumStore";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useAddSubsectionStore } from "./store/mcAddSubsectionStore";
import { useDescriptionEditor } from "../composable/useDescriptionEditor";

const props = defineProps({
    /**
     * to gradually add more marginLeft to indicate node level depth
     */
    depth: {
        type: Number,
        required: true,
    },
});

const store = useStore();
const activeNodeStore = useActiveNodeStore();
const addSubsectionStore = useAddSubsectionStore();

/**
 * to gradually and more marginLeft to indicate node level depth
 *
 * @returns { Number }
 */
const marginLeft = window.Vue.computed(() => `calc(${props.depth * 2}rem)`);

/**
 * Store a reference to the ckeditor used for the description
 *
 * @returns { String }
 */
const ckeditorIdAttr = window.Vue.computed(() => `description-${activeNodeStore.node.id}`);

const addButton = ref<HTMLButtonElement | null>(null);

/**
 * To instantly notify the user that the subsection is being added
 * by updating the button text
 *
 * @returns { String }
 */
const addButtonText = window.Vue.computed(() => (addSubsectionStore.isProcessing ? "Adding..." : "Add"));

/**
 * Only enable the button once the conditions are met
 *
 * @returns { Boolean }
 */
const isAddDisabled = window.Vue.computed(() => {
    if (addButton.value !== null && addButton.value.hasAttribute("data-ckEditorMaxCharCountExceededAtLastCheck")) {
        return true;
    }

    return addSubsectionStore.subsection.name?.trim().length === 0 || addSubsectionStore.isProcessing;
});

/**
 * Use a customised ckeditor for the description field
 */
const descriptionField = useDescriptionEditor(
    addSubsectionStore.subsection?.description as string,
    ckeditorIdAttr.value,
    addButton,
    isAddDisabled
);

/**
 * And watch for changes on this field, so we can update the description in the store
 */
window.Vue.watch(descriptionField, (newValue) => {
    addSubsectionStore.subsection.description = newValue;
});

/**
 * submit save
 */
function onAddSubsectionSelected() {
    addSubsectionStore.add();
}

/**
 * to display the toast on subsection added / failed
 */
addSubsectionStore.$onAction(({ name, after, onError }) => {
    if (name === "add") {
        store.toastEl.value.activate("Adding...");
        after((result) => {
            store.toastEl.value.timeout(result.message, true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.value.timeout("Unable to add subsection", false, 5000);
        });
    }
});

/**
 * close the addSubsection view
 */
function onCancelSelected() {
    addSubsectionStore.cancel();
}
</script>

<template>
    <div class="node-container" :style="{ marginLeft: marginLeft }">
        <section>
            <fieldset class="content">
                <legend class="title">Add subsection</legend>
                <div class="small-12 columns">
                    <label for="name" class="required">Name</label>
                    <input
                        type="text"
                        id="name"
                        v-model="addSubsectionStore.subsection.name"
                        maxlength="2048"
                        required
                    />
                </div>
                <div class="small-12 columns">
                    <label for="code">Code</label>
                    <input type="text" id="code" v-model="addSubsectionStore.subsection.code" maxlength="64" />
                </div>
                <div class="small-12 columns">
                    <label for="description">Description</label>
                    <textarea :id="ckeditorIdAttr" rows="6"></textarea>
                </div>
                <div class="small-12 columns">
                    <ul class="flex-list">
                        <li>
                            <button
                                class="button icon-close"
                                :disabled="addSubsectionStore.isProcessing"
                                @click.prevent="onCancelSelected"
                            >
                                Cancel
                            </button>
                        </li>
                        <li>
                            <button
                                class="button icon-add submit"
                                :data-submit-button-for="ckeditorIdAttr"
                                ref="addButton"
                                data-test-add-subsection
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
</template>

<style lang="scss" scoped>
@import "./style/mcActiveNode.scss";
</style>
