<script setup lang="ts">
/**
 * to be able to update a nodes name, code and description
 */

import { useStore } from "../store/manageCurriculumStore";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useUpdateNodeStore } from "./store/mcUpdateNodeStore";
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
const updateNodeStore = useUpdateNodeStore();

/**
 * to gradually and more marginLeft to indicate node level depth
 *
 * @returns { number }
 */
const marginLeft = window.Vue.computed(() => `calc(${props.depth * 2}rem)`);

/**
 * Store a reference to the ckeditor used for the description
 *
 * @returns { string }
 */
const ckeditorIdAttr = window.Vue.computed(() => `description-${activeNodeStore.node.id}`);

const updateButton = ref<HTMLButtonElement | null>(null);
let isMaxCharCountExceeded = ref<boolean>(false);

/**
 * To instantly notify the user that the node is being updated
 * by updating the button text
 *
 * @returns { string }
 */
const updateButtonText = window.Vue.computed(() => (updateNodeStore.isProcessing ? "Updating..." : "Update"));

/**
 * Only enable the button once the conditions are met
 *
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
    updateButton,
    isMaxCharCountExceeded
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
function onUpdateSelected() {
    updateNodeStore.update();
}

/**
 * to display the toast on subsection added / failed
 */
updateNodeStore.$onAction(({ name, after, onError }) => {
    if (name === "update") {
        store.toastEl.value.activate("Updating...");
        after((result) => {
            store.toastEl.value.timeout(result.message, true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.value.timeout("Unable to update node", false, 5000);
        });
    }
});

/**
 * close the update view
 */
function onCancelSelected() {
    updateNodeStore.cancel();
}
</script>

<template>
    <div class="node-container" :style="{ marginLeft: marginLeft }">
        <section>
            <form>
                <fieldset class="content">
                    <legend class="title">Update</legend>
                    <div class="small-12 columns">
                        <label for="name" class="required">Name</label>
                        <input
                            id="name"
                            v-model="updateNodeStore.activeNodeCopy.name"
                            type="text"
                            maxlength="2048"
                            required
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
                                    class="button icon-modify submit"
                                    :data-submit-button-for="ckeditorIdAttr"
                                    :disabled="isUpdateDisabled"
                                    ref="updateButton"
                                    data-test-update-node
                                    @click.prevent="onUpdateSelected"
                                >
                                    {{ updateButtonText }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </fieldset>
            </form>
        </section>
    </div>
</template>

<style lang="scss" scoped>
@import "./style/mcActiveNode.scss";
</style>
