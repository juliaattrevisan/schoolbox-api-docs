<script setup lang="ts">
/**
 * To finalise review, first notifying if there are any items that have not been marked
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";

const store = useStore();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
const modalEl = window.Vue.ref<typeof RevealModal | null>();
const isFinalising = window.Vue.ref(false); // to show the finalising text on the button
const buttonText = window.Vue.computed(() => {
    // to change the button text when finalising
    return isFinalising.value ? "Finalising..." : "Finalise Review";
});
const confirmButtonText = window.Vue.computed(() => {
    // to change the button text when finalising
    return isFinalising.value ? "Finalising..." : "Yes";
});

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
 * To mark all attempts as reviewed
 * @returns {FormData} - the data to send through
 */
function getFormData() {
    const formData = new FormData();
    // add the reference for each attempt
    studentsStore.students.forEach((student) => {
        formData.append("learnosityReferences[]", student.attemptComment.learnosityReference);
    });
    return formData;
}

/**
 * Submit the feedback then redirect to the status page
 */
async function submitFeedback() {
    isFinalising.value = true;
    try {
        await fetch(`${store.postFeedback}`, {
            method: "POST",
            body: getFormData(),
        });
    } catch (error) {
        console.error(error);
    } finally {
        isFinalising.value = false;
        window.location.href = store.statusPage;
    }
}

/**
 * To finalise review, first notifying if there are any items that have not been marked
 */
function onSelected() {
    if (studentsStore.allStudentsMarked) {
        void submitFeedback();
    } else {
        store.isFinaliseReviewModalOpen = true;
    }
}

/**
 * Submit the feedback
 */
function onConfirmSelected() {
    void submitFeedback();
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
        {{ buttonText }}
    </button>
    <reveal-modal ref="modalEl" :close-on-background-click="false" @closed="onCancelSelected">
        <h2>Unmarked attempts</h2>
        <p>
            There are currently attempts that have not yet been completely marked. Are you sure you wish to finalise the
            review?
        </p>
        <ul class="flex-list">
            <li>
                <button class="button icon-approve submit" @click.prevent="onConfirmSelected">
                    {{ confirmButtonText }}
                </button>
            </li>
            <li>
                <button class="button icon-close" @click.prevent="onCancelSelected">Cancel</button>
            </li>
        </ul>
    </reveal-modal>
</template>
