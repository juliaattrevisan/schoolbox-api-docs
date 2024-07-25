<script setup lang="ts">
/**
 * To finalise review, first notifying if there are any items that have not been marked
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
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
 * This ensures the mark is the latest valid submission.
 * If the user is quick enough, they can update the mark and press submit before
 * the mark is submitted to the back end.
 * @param {Student} student - the student to get the mark from
 * @returns {string} - the mark to submit
 */
function getMark(student: Student) {
    const gradeContainer = document.getElementById(`grade-container-${student.id}`) as HTMLDivElement;
    const formElement = gradeContainer.querySelector("input,select") as HTMLFormElement; // depending on the Mark Type, the mark field can either be an input or a select
    if (gradeContainer === null || formElement === null) {
        return student.attemptResponse?.mark ?? "";
    }
    if (formElement.getAttribute("aria-invalid") === "false" && student.attemptResponse?.mark !== formElement.value) {
        useStudentStore().updateAttemptMark(student, formElement.value as string);
        return formElement.value as string;
    }
    return student.attemptResponse?.mark ?? "";
}

/**
 * To mark the attempt as reviewed
 * @returns {FormData} - the data to send through
 */
function getFormData() {
    const formData = new FormData();
    formData.append("responseId", studentStore.student.attemptResponse?.responseId ?? "");
    formData.append("maxMarks", studentStore.student.attemptResponse?.maxMarks?.toString() ?? "");
    formData.append("mark", getMark(studentStore.student));
    formData.append("comment", studentStore.student.attemptResponse?.comment ?? "");
    formData.append("publish", "1");
    return formData;
}

/**
 * Submit the feedback then redirect to the status page
 */
async function submitFeedback() {
    isFinalising.value = true;
    activate("Finalising review...");
    try {
        const response = await fetch(`${studentStore.student.urls?.postFinaliseAttempt}`, {
            method: "POST",
            headers: {
                Accept: "application/json",
            },
            body: getFormData(),
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
