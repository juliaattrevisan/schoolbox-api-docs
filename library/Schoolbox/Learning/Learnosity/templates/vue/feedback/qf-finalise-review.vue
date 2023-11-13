<script setup lang="ts">
/**
 * To finalise review, first notifying if there are any items that have not been marked
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useStore } from "./store/quizFeedbackStore";
import { useStudentStore } from "./store/qfStudentStore";

const store = useStore();
const studentStore = useStudentStore();
const modalEl = window.Vue.ref<typeof RevealModal | null>();

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => store.isFinaliseReviewModalOpen,
    (isOpen) => {
        if (isOpen) {
            modalEl.value?.open();
        } else {
            modalEl.value?.close();
        }
    }
);

/**
 * To finalise review, first notifying if there are any items that have not been marked
 */
function onSelected() {
    if (studentStore.student.isMarked) {
        window.location.href = store.statusPage;
    } else {
        store.isFinaliseReviewModalOpen = true;
    }
}

/**
 * Navigate to appropriate URL
 */
function onConfirmSelected() {
    window.location.href = store.statusPage;
}

/**
 * Close the modal
 */
function onCancelSelected() {
    store.isFinaliseReviewModalOpen = false;
}
</script>

<template>
    <button class="button full-width icon-approve submit" :disabled="!store.isLoaded" @click.prevent="onSelected">
        Finalise Review
    </button>
    <reveal-modal ref="modalEl" :close-on-background-click="false" @closed="onCancelSelected">
        <h2>Unmarked cards</h2>
        <p>There are currently cards that have not yet been marked. Are you sure you wish to finalise the review?</p>
        <ul class="flex-list">
            <li>
                <button class="button icon-approve submit" @click.prevent="onConfirmSelected">Yes</button>
            </li>
            <li>
                <button class="button icon-close" @click.prevent="onCancelSelected">Cancel</button>
            </li>
        </ul>
    </reveal-modal>
</template>
