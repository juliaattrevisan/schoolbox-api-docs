<script setup lang="ts">
/**
 * to show further info in a modal on a code that's been selected
 */

import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import { getButtonTextDisabledDueToStorageLimitSuffix } from "@js/editor/settings/wordCountSettings";
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

const activeNodeStore = useActiveNodeStore();
const addSubsectionStore = useAddSubsectionStore();

/**
 * to gradually and more marginLeft to indicate node level depth
 * @returns { number }
 */
const marginLeft = window.Vue.computed(() => `calc(${props.depth * 2}rem)`);

/**
 * Store a reference to the ckeditor used for the description
 * @returns { string }
 */
const ckeditorIdAttr = window.Vue.computed(() => `description-${activeNodeStore.node.id}`);

const isMaxCharCountExceeded = window.Vue.ref<boolean>(false);

/**
 * To update the button text as the component state changes.
 * @returns { string }
 */
const addButtonText = window.Vue.computed(() => {
    if (addSubsectionStore.isProcessing) {
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
        addSubsectionStore.subsection.name?.trim().length === 0 ||
        addSubsectionStore.isProcessing
    );
});

/**
 * Use a customised ckeditor for the description field
 */
const descriptionField = useDescriptionEditor(
    addSubsectionStore.subsection?.description as string,
    ckeditorIdAttr.value,
    isMaxCharCountExceeded,
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
async function onAddSubsectionSelected() {
    await addSubsectionStore.add();
}

/**
 * to display the toast on subsection added / failed
 */
addSubsectionStore.$onAction(({ name, after, onError }) => {
    if (name === "add") {
        toast.activate("Adding...");

        after((result) => {
            toast.timeout({ text: result.message, isSuccess: true });
        });
        onError((error) => {
            console.error("Unable to add subsection", error);
            toast.timeout({ text: "Unable to add subsection", isSuccess: false });
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
        <form>
            <section>
                <fieldset class="content">
                    <legend class="title">Add subsection</legend>
                    <div class="small-12 columns">
                        <label for="name" class="required">Name</label>
                        <input
                            id="name"
                            v-model="addSubsectionStore.subsection.name"
                            maxlength="2048"
                            required
                            type="text"
                        />
                    </div>
                    <div class="small-12 columns">
                        <label for="code">Code</label>
                        <input id="code" v-model="addSubsectionStore.subsection.code" type="text" maxlength="64" />
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
                                    ref="addButton"
                                    class="button icon-add submit"
                                    data-test-add-subsection
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
</template>

<style lang="scss" scoped>
@import "./style/mcActiveNode.scss";
</style>
