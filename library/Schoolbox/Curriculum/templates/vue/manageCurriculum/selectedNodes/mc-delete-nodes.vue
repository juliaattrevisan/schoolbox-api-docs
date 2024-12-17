<script setup lang="ts">
/**
 * To be able to delete a node on confirmation
 */

import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useSelectedNodesStore } from "./store/mcSelectedNodesStore";
import { useDeleteNodesStore } from "./store/mcDeleteNodesStore";

const selectedNodesStore = useSelectedNodesStore();
const deleteNodesStore = useDeleteNodesStore();
const modalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>(null);

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => deleteNodesStore.isOpen,
    (isOpen) => {
        if (modalEl.value === null) return; // no modal yet...
        if (isOpen) {
            modalEl.value.open();
        } else {
            modalEl.value.close();
        }
    },
);

/**
 * delete the node when confirmed
 */
async function onDeleteSelected(): Promise<void> {
    await deleteNodesStore.deleteNodes();
}

/**
 * so we can track the progress of the node removal
 * and update the UI accordingly
 */
deleteNodesStore.$onAction(({ name, after, onError }) => {
    if (name === "deleteNodes") {
        toast.activate("Deleting...");

        after((result) => {
            toast.timeout({ text: result, isSuccess: true });
        });
        onError((error) => {
            console.error("Unable to delete outcome", error);
            toast.timeout({ text: "Unable to delete outcome", isSuccess: false });
        });
    }
});

/**
 * To instantly notify the user that the node is being deleted
 * by updating the button text
 * @returns { string }
 */
const deleteButtonText = window.Vue.computed(() => (deleteNodesStore.isProcessing ? "Deleting..." : "Yes"));

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place
 * @returns { boolean }
 */
const isModalDisabled = window.Vue.computed(() => deleteNodesStore.isProcessing);

/**
 * to close the modal
 */
function onCancelSelected(): void {
    deleteNodesStore.cancel();
}

/**
 * ensure the isOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed(): void {
    deleteNodesStore.cancel();
}
</script>

<template>
    <reveal-modal
        ref="modalEl"
        :is-close-disabled="isModalDisabled"
        :close-on-background-click="false"
        @closed="onModalClosed"
    >
        <h2>Please Confirm</h2>
        <p>
            Are you sure you wish to delete the selected {{ selectedNodesStore.singleOrPlural }} and all of
            {{ selectedNodesStore.count > 1 ? "their" : "its" }} children?
        </p>
        <ul class="flex-list">
            <li>
                <button
                    class="button icon-approve submit"
                    data-test="confirm-delete-node"
                    :disabled="deleteNodesStore.isProcessing"
                    @click.prevent="onDeleteSelected"
                >
                    {{ deleteButtonText }}
                </button>
            </li>
            <li>
                <button
                    class="button icon-close"
                    data-test-cancel-delete-node
                    :disabled="deleteNodesStore.isProcessing"
                    @click.prevent="onCancelSelected"
                >
                    Cancel
                </button>
            </li>
        </ul>
    </reveal-modal>
</template>

<style scoped></style>
