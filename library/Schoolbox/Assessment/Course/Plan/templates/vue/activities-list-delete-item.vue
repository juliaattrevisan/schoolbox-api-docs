<script setup lang="ts">
/**
 * To be able to delete an activity on confirmation
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useDeleteActivityStore } from "./store/unitPlannerDeleteActivityStore";

const unitPlannerStore = useUnitPlannerStore();
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
                modalEl.value.open();
            } else {
                modalEl.value.close();
            }
        }
    },
);

/**
 * so we can track the progress of the activity removal
 * and update the UI accordingly
 */
deleteActivityStore.$onAction(({ name, after, onError }) => {
    if (name === "deleteActivity" && unitPlannerStore.toastEl !== null) {
        unitPlannerStore.toastEl.activate("Deleting...");
        after((result) => {
            if (unitPlannerStore.toastEl !== null) {
                unitPlannerStore.toastEl.timeout(result, true, 5000);
            }
        });
        onError((error) => {
            console.warn(error);
            unitPlannerStore.toastEl.timeout("Server error", false, 5000);
        });
    }
});

/**
 * To instantly notify the user that the node is being deleted
 * by updating the button text
 * @returns {string} The button text
 */
const deleteButtonText = window.Vue.computed(() =>
    deleteActivityStore.isProcessing ? "Deleting..." : "Yes, permanently delete",
);

/**
 * To prevent the user from moving away from the modal while processing is taking place
 * @returns {boolean} whether the modal is disabled
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
        <p>Are you sure you wish to delete this activity?</p>
        <div class="alert-box alert">WARNING: All student responses, feedback, and marks will also be deleted.</div>
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
