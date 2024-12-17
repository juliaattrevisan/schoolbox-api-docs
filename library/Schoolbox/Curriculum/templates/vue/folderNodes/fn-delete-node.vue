<script setup lang="ts">
/**
 * To confirm the deletion of a node
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";

interface Props {
    folderId: number; // The folder id to display the nodes for
    componentInstanceId: number; // The component instance id to display the nodes on
}
const props = defineProps<Props>();

const store = useStore(props.folderId, props.componentInstanceId);
const modalEl = window.Vue.ref<typeof RevealModal | null>(null);

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => store().nodeToRemove,
    (nodeToRemove) => {
        if (modalEl.value !== null) {
            if (nodeToRemove !== null) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- RevealModal is untyped
                modalEl.value.open();
            } else {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- RevealModal is untyped
                modalEl.value.close();
            }
        }
    },
);

/**
 * delete the node when confirmed
 */
function onDeleteSelected(): void {
    void store().confirmRemoveNode();
}

/**
 * To instantly notify the user that the node is being deleted
 * by updating the button text
 * @returns {string}
 */
const deleteButtonText = window.Vue.computed(() => (store().isProcessing ? "Deleting..." : "Yes"));

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place
 * @returns {boolean}
 */
const isModalDisabled = window.Vue.computed(() => store().isProcessing);

/**
 * to close the modal
 */
function onModalClosed(): void {
    store().cancelRemoveNode();
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
        <p>Are you sure you wish to remove this outcome?</p>
        <ul class="flex-list">
            <li>
                <button
                    class="button icon-approve submit"
                    data-test="confirm-delete-node"
                    :disabled="store().isProcessing"
                    @click.prevent="onDeleteSelected"
                >
                    {{ deleteButtonText }}
                </button>
            </li>
            <li>
                <button
                    class="button icon-close"
                    data-test-cancel-delete-node
                    :disabled="store().isProcessing"
                    @click.prevent="onModalClosed"
                >
                    Cancel
                </button>
            </li>
        </ul>
    </reveal-modal>
</template>

<style scoped></style>
