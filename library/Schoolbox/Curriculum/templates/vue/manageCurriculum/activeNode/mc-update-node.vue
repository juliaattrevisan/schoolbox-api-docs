<script setup lang="ts">
/**
 * to be able to update a nodes name, code and description
 */

import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import { getButtonTextDisabledDueToStorageLimitSuffix } from "@js/editor/settings/wordCountSettings";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useDescriptionEditor } from "../composable/useDescriptionEditor";
import { useUpdateNodeStore } from "./store/mcUpdateNodeStore";

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
const updateNodeStore = useUpdateNodeStore();

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
const updateButtonText = window.Vue.computed(() => {
    if (updateNodeStore.isProcessing) {
        return "Updating...";
    }

    return `Update${isMaxCharCountExceeded.value ? getButtonTextDisabledDueToStorageLimitSuffix() : ""}`;
});

/**
 * Only enable the button once the conditions are met
 * @returns { boolean }
 */
const isUpdateDisabled = window.Vue.computed(() => {
    return (
        isMaxCharCountExceeded.value ||
        updateNodeStore.activeNodeCopy.name.trim().length === 0 ||
        updateNodeStore.isProcessing
    );
});

/**
 * Use a customised ckeditor for the description field
 */
const descriptionField = useDescriptionEditor(
    updateNodeStore.activeNodeCopy?.description as string,
    ckeditorIdAttr.value,
    isMaxCharCountExceeded,
);

/**
 * And watch for changes on this field, so we can update the description in the store
 */
window.Vue.watch(descriptionField, (newValue) => {
    updateNodeStore.activeNodeCopy.description = newValue;
});

/**
 * submit save
 */
async function onUpdateSelected(): Promise<void> {
    await updateNodeStore.update();
}

/**
 * to display the toast on subsection added / failed
 */
updateNodeStore.$onAction(({ name, after, onError }) => {
    if (name === "update") {
        toast.activate("Updating...");
        after((result) => {
            toast.timeout({ text: result.message, isSuccess: true });
        });
        onError((error) => {
            console.error("Unable to update node", error);
            toast.timeout({ text: "Unable to update node", isSuccess: false });
        });
    }
});

/**
 * close the update view
 */
function onCancelSelected(): void {
    updateNodeStore.cancel();
}
</script>

<template>
    <div class="node-container" :style="{ marginLeft: marginLeft }">
        <form>
            <section>
                <fieldset class="content">
                    <legend class="title">Update</legend>
                    <div class="small-12 columns">
                        <label for="name" class="required">Name</label>
                        <input
                            id="name"
                            v-model="updateNodeStore.activeNodeCopy.name"
                            maxlength="2048"
                            required
                            type="text"
                        />
                    </div>
                    <div class="small-12 columns">
                        <label for="code">Code</label>
                        <input id="code" v-model="updateNodeStore.activeNodeCopy.code" type="text" maxlength="64" />
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
                                    :disabled="updateNodeStore.isProcessing"
                                    @click.prevent="onCancelSelected"
                                >
                                    Cancel
                                </button>
                            </li>
                            <li>
                                <button
                                    ref="updateButton"
                                    class="button icon-modify submit"
                                    data-test-update-node
                                    :data-submit-button-for="ckeditorIdAttr"
                                    :disabled="isUpdateDisabled"
                                    @click.prevent="onUpdateSelected"
                                >
                                    {{ updateButtonText }}
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
