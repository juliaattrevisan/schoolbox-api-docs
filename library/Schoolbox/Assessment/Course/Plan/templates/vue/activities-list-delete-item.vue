<script setup lang="ts">
/**
 * To be able to delete an activity on confirmation
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useDeleteActivityStore } from "./store/unitPlannerDeleteActivityStore";

console.info("activities-list-delete-item.vue calling useUnitPlannerStore()");
const unitPlannerStore = useUnitPlannerStore();
const deleteActivityStore = useDeleteActivityStore();
const modalEl = window.Vue.ref(null);

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => deleteActivityStore.isOpen,
    (isOpen) => {
        isOpen ? modalEl.value.open() : modalEl.value.close();
    },
);
console.info("activities-list-delete-item.vue onAction() 3");

/**
 * delete the node when confirmed
 */
function onConfirmDeleteActivity() {
    deleteActivityStore.deleteActivity();
}

/**
 * so we can track the progress of the activity removal
 * and update the UI accordingly
 */
deleteActivityStore.$onAction(({ name, after, onError }) => {
    console.info("activities-list-delete-item.vue onAction() e");
    console.info("activities-list-delete-item.vue onAction() name, after, onError: ", name, after, onError);
    console.info("activities-list-delete-item.vue onAction() unitPlannerStore: ", unitPlannerStore);
    console.info("activities-list-delete-item.vue onAction() unitPlannerStore.toastEl: ", unitPlannerStore.toastEl);
    if (name === "deleteActivity") {
        console.info("activities-list-delete-item.vue onAction() 1");
        unitPlannerStore.toastEl.activate("Deleting...");
        console.info("activities-list-delete-item.vue onAction() 2");
        after((result) => {
            console.info("activities-list-delete-item.vue onAction() after() e");
            unitPlannerStore.toastEl.timeout(result, true, 5000);
            console.info("activities-list-delete-item.vue onAction() after() x");
        });
        onError((error) => {
            console.warn(error);
        });
    }
    console.info("activities-list-delete-item.vue onAction() x");
});

/**
 * To instantly notify the user that the node is being deleted
 * by updating the button text
 *
 * @returns { String }
 */
const deleteButtonText = window.Vue.computed(() =>
    deleteActivityStore.isProcessing ? "Deleting..." : "Yes, permanently delete",
);

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place
 *
 * @returns { Boolean }
 */
const isModalDisabled = window.Vue.computed(() => deleteActivityStore.isProcessing);

/**
 * to close the modal
 */
function onCancelDeleteActivity() {
    deleteActivityStore.cancel();
}

/**
 * ensure the isOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed() {
    deleteActivityStore.cancel();
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
        <p>Are you sure you wish to delete this activity?</p>
        <div class="alert-box alert">WARNING: All student responses, feedback, and marks will also be deleted.</div>
        <ul class="flex-list">
            <li>
                <button
                    class="button icon-approve submit"
                    data-test-confirm-delete-activity
                    :disabled="deleteActivityStore.isProcessing"
                    @click.prevent="onConfirmDeleteActivity"
                >
                    {{ deleteButtonText }}
                </button>
            </li>
            <li>
                <button
                    class="button icon-close"
                    data-test-cancel-delete-activity
                    :disabled="deleteActivityStore.isProcessing"
                    @click.prevent="onCancelDeleteActivity"
                >
                    No, keep it
                </button>
            </li>
        </ul>
    </reveal-modal>
</template>

<style scoped></style>
