<script setup lang="ts">
/**
 * To finalise review, first notifying if there are any items that have not been marked
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { useStore } from "./store/quizFeedbackStore";
import { useStudentStore } from "./store/qfStudentStore";
import { getFinaliseReviewFormData } from "../student/util/finaliseReviewUtils";

const store = useStore();
const studentStore = useStudentStore();
const modalEl = window.Vue.ref<typeof RevealModal | null>();
const isFinalising = window.Vue.ref(false); // to show the finalising text on the button

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
const buttonText = window.Vue.computed(() => {
    if (studentStore.student.isReviewed) {
        return isFinalising.value ? "Updating..." : "Update review";
    }
    // to change the button text when finalising
    return isFinalising.value ? "Finalising..." : "Finalise review";
});

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
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
    },
);

/**
 * Submit the feedback then redirect to the status page
 */
async function submitFeedback(): Promise<void> {
    isFinalising.value = true;
    activate("Finalising review...");
    try {
        const response = await fetch(`${studentStore.student.urls?.postFinaliseAttempt}`, {
            method: "POST",
            headers: {
                Accept: "application/json",
            },
            body: getFinaliseReviewFormData(studentStore.student),
        });
        if (response.ok) {
            window.location.href = store.statusPage;
            timeout({ text: "Review successfully finalised" });
        } else {
            timeout({
                text: "Review could not be finalised, please contact your support if the problem persists",
                isSuccess: false,
            });
        }
    } catch (error) {
        timeout({
            text: "Review could not be finalised, please contact your support if the problem persists",
            isSuccess: false,
        });
    } finally {
        isFinalising.value = false;
    }
}

/**
 * To finalise review, first notifying if there are any items that have not been marked
 */
function onSelected(): void {
    if (studentStore.student.isMarked) {
        void submitFeedback();
    } else {
        store.isFinaliseReviewModalOpen = true;
    }
}

/**
 * Submit the feedback
 */
function onConfirmSelected(): void {
    void submitFeedback();
}

/**
 * Close the modal
 */
function onCancelSelected(): void {
    store.isFinaliseReviewModalOpen = false;
}
</script>

<template>
    <button
        class="button full-width icon-approve submit"
        :disabled="!store.isLoaded || store.isProcessing"
        @click.prevent="onSelected"
    >
        {{ buttonText }}
    </button>
    <reveal-modal ref="modalEl" :close-on-background-click="false" @closed="onCancelSelected">
        <h2>Unmarked items</h2>
        <p>
            There are currently items that have not been marked. Are you sure you wish to
            {{ studentStore.student.isReviewed ? "update" : "finalise" }} the review?
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
../student/util/finaliseReviewUtils
