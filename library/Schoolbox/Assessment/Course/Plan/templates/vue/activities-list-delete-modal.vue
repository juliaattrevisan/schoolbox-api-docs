<script setup lang="ts">
/**
 * To be able to delete an activity on confirmation
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useDeleteActivityStore } from "./store/unitPlannerDeleteActivityStore";

const deleteActivityStore = useDeleteActivityStore();
const modalEl = window.Vue.ref<typeof RevealModal | null>(null);

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => deleteActivityStore.isOpen,
    (isOpen) => {
        if (modalEl.value !== null) {
            if (isOpen) {
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
 * To instantly notify the user that the node is being deleted
 * by updating the button text
 */
const deleteButtonText = window.Vue.computed(() =>
    deleteActivityStore.isProcessing ? "Deleting..." : "Yes, permanently delete",
);

/**
 * To prevent the user from moving away from the modal while processing is taking place
 */
const isModalDisabled = window.Vue.computed(() => deleteActivityStore.isProcessing);
</script>

<template>
    <reveal-modal
        ref="modalEl"
        :is-close-disabled="isModalDisabled"
        :close-on-background-click="false"
        @closed="deleteActivityStore.cancel"
    >
        <h2>Please Confirm</h2>
        <p>Are you sure you wish to delete this activity? You will be unable to import this activity into classes.</p>
        <ul class="flex-list">
            <li>
                <button
                    class="button icon-approve submit"
                    data-test-confirm-delete-activity
                    :disabled="deleteActivityStore.isProcessing"
                    @click.prevent="deleteActivityStore.deleteActivity"
                >
                    {{ deleteButtonText }}
                </button>
            </li>
            <li>
                <button
                    class="button icon-close"
                    data-test-cancel-delete-activity
                    :disabled="deleteActivityStore.isProcessing"
                    @click.prevent="deleteActivityStore.cancel"
                >
                    No, keep it
                </button>
            </li>
        </ul>
    </reveal-modal>
</template>

<style scoped></style>
