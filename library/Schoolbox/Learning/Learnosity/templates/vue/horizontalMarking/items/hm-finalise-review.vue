<script setup lang="ts">
/**
 * To finalise review, first notifying if there are any items that have not been marked
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import { getFinaliseReviewFormData } from "../../student/util/finaliseReviewUtils";

const store = useStore();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
const modalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>();
const isFinalising = window.Vue.ref(false); // to show the finalising text on the button
const buttonText = window.Vue.computed(() => {
    // to change the button text when finalising
    return isFinalising.value ? "Finalising..." : "Finalise review";
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
    },
);

/**
 * Get the list of all students to post feedback for
 * @returns - the list of fetches to make
 */
function getFetches(): Promise<Response>[] {
    const fetchList: Promise<Response>[] = [];
    studentsStore.students.forEach((student) => {
        fetchList.push(
            fetch(`${student.urls?.postFinaliseAttempt}`, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                },
                body: getFinaliseReviewFormData(student),
            }),
        );
    });
    return fetchList;
}

/**
 * Submit the feedback then redirect to the status page
 */
async function submitFeedback(): Promise<void> {
    isFinalising.value = true;
    activate("Finalising review...");
    try {
        const feedbacks = await Promise.all(getFetches());
        const responses = feedbacks.map((response) => response.ok);
        const isAllResponsesOk = responses.every((response) => response === true);
        if (isAllResponsesOk) {
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
    if (studentsStore.allStudentsMarked) {
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
        v-if="itemsStore.isAttemptComment"
        class="button full-width icon-approve submit"
        :disabled="!store.isLoaded || store.isProcessing"
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
../../student/util/finaliseReviewUtils
