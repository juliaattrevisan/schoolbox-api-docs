<script setup lang="ts">
/**
 * To provide a final comment response for a student
 */

import { watchDebounced } from "@vueuse/core";
import { timeout } from "@js/util/jsUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";

interface Props {
    student: Student; // the student to display
    isReadOnly: boolean; // whether the question is readonly, we don't need to init the ckeditor if it's readonly
}
const props = defineProps<Props>();

const editorId = `final-review-comment-${props.student.elementId}`;
const comment = window.Vue.ref(props.student.attemptResponse.comment ?? "");
const isSaving = window.Vue.ref(false);
const hasSaved = window.Vue.ref(false);
const hasError = window.Vue.ref(false);

const emit = defineEmits<{
    updatedComment: [comment: string];
}>();

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
const saveText = window.Vue.computed(() => {
    if (isSaving.value) {
        return "Saving...";
    }
    if (hasError.value) {
        return "Comment could not be saved";
    }
    return "Comment saved";
});

/**
 * To open and close the save status
 * @returns {string} - the grid-template-rows
 */
const saveContainerHeight = window.Vue.computed(() => {
    if (isSaving.value || hasSaved.value || hasError.value) {
        return "1fr";
    }
    return "0fr";
});

/**
 * To notify the user that they're leaving the page while data is being saved
 * @param event - the event to prevent
 */
function beforeUnloadHandler(event: BeforeUnloadEvent): void {
    event.preventDefault();
    event.returnValue = "";
}

/**
 * Save the comment if it's changed
 */
async function saveComment(): Promise<void> {
    if (isSaving.value) {
        return;
    }
    if (comment.value === props.student.attemptResponse.comment) {
        return;
    }
    if (props.student.urls === undefined) {
        return;
    }
    try {
        isSaving.value = true;
        window.addEventListener("beforeunload", beforeUnloadHandler);
        const formData = new FormData();
        formData.append("comment", comment.value);
        const response = await fetch(props.student.urls.postFeedbackAttemptComment, {
            method: "POST",
            body: formData,
        });
        if (response.ok) {
            emit("updatedComment", comment.value);
            hasSaved.value = true;
        } else {
            hasError.value = true;
        }
        window.removeEventListener("beforeunload", beforeUnloadHandler);
        isSaving.value = false;
    } catch (error) {
        hasError.value = true;
    }
    await timeout(2000);
    hasSaved.value = false;
    hasError.value = false;
}

/**
 * save the comment when it's changed, debouncing to prevent multiple saves,
 * only saving when the user has stopped typing for 1 second
 */
watchDebounced(
    comment,
    () => {
        void saveComment();
    },
    { debounce: 1000 },
);

/**
 * Save any unsaved changes
 */
window.Vue.onUnmounted(async () => {
    await saveComment();
});
</script>

<template>
    <div class="l-flex__item--1-grow">
        <div
            v-if="
                (props.isReadOnly && props.student.attemptResponse.comment === null) ||
                (props.isReadOnly && props.student.attemptResponse.comment === '')
            "
            class="l-flex-col l-gap-s"
        >
            <label>Comment</label>
            <p>
                <em>No comment added.</em>
            </p>
        </div>
        <div v-else-if="props.isReadOnly && props.student.attemptResponse.comment !== ''" class="l-flex-col l-gap-s">
            <label>Comment</label>
            <p>{{ props.student.attemptResponse.comment }}</p>
        </div>
        <template v-else>
            <label :for="editorId">Comment</label>
            <textarea :id="editorId" v-model="comment" rows="3" class="comment-input f-no-margins"></textarea>
            <div class="save-container l-grid">
                <div class="save-container__inner-container">
                    <div :class="`alert-box f-no-margins ${hasSaved ? 'success' : ''} ${hasError ? 'alert' : ''}`">
                        <em>{{ saveText }}</em>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<style lang="scss" scoped>
.comment-input {
    resize: vertical; // only allow the textarea to be resized vertically
}

.save-container {
    grid-template-rows: v-bind(saveContainerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}
</style>
