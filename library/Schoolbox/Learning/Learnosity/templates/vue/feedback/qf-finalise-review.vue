<script setup lang="ts">
/**
 * To finalise review, first notifying if there are any items that have not been marked
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { getItemScore } from "@schoolboxLearnosity/js/util/markingUtils";
import { useStore } from "./store/quizFeedbackStore";
import { useStudentStore } from "./store/qfStudentStore";

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
        return isFinalising.value ? "Updating..." : "Update Review";
    }
    // to change the button text when finalising
    return isFinalising.value ? "Finalising..." : "Finalise Review";
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
    }
);

/**
 * To mark the attempt as reviewed
 * @returns {FormData} - the data to send through
 */
function getFormData() {
    const formData = new FormData();
    // run through all the items and add the total score and comment for each item
    studentStore.student.items.forEach((item) => {
        formData.append(`scores[${item.reference}]`, getItemScore(item).toString());
        formData.append(`comment[${item.reference}]`, item.questions[0].response?.comment ?? "");
    });
    formData.append(`attempt-comment`, studentStore.student.attemptComment.comment ?? ""); //
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
    if (studentStore.student.isMarked) {
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
