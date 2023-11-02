<script setup lang="ts">
/**
 * To move to the next item (card) if available
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";

const store = useStore();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
const modalEl = window.Vue.ref(null);

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => store.isFinaliseReviewModalOpen,
    (isOpen) => {
        isOpen ? modalEl.value.open() : modalEl.value.close();
    }
);

/**
 * To finalise review, first notifying if there are any items that have not been marked
 */
function onSelected() {
    if (studentsStore.allStudentsMarked) {
        // navigate to appropriate URL
    } else {
        store.isFinaliseReviewModalOpen = true;
    }
}

/**
 * Navigate to appropriate URL
 */
function onConfirmSelected() {
    // navigate to appropriate URL
}

/**
 * Close the modal
 */
function onCancelSelected() {
    store.isFinaliseReviewModalOpen = false;
}
</script>

<template>
    <button
        v-if="itemsStore.isAttemptComment"
        class="button full-width icon-approve submit"
        :disabled="!store.isLoaded"
        @click.prevent="onSelected"
    >
        Finalise Review
    </button>
    <reveal-modal ref="modalEl" :close-on-background-click="false" @closed="onCancelSelected">
        <h2>Unmarked attempts</h2>
        <p>
            There are currently attempts that have not yet been completely marked. Are you sure you wish to finalise the
            review?
        </p>
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
