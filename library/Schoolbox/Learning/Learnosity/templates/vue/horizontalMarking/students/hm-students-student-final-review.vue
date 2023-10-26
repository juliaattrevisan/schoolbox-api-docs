<script setup lang="ts">
/**
 * To provide the opportunity for a final comment
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import type { Student } from "../types/Student";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();
const studentsStore = useStudentsStore();
const itemsStore = useItemsStore();
const editorId = `final-review-comment-${props.student.elementId}`;
const isMaxCharCountExceeded = window.Vue.ref<boolean>(false);
const comment = useEditor(props.student.attemptResponse.comment ?? "", editorId, isMaxCharCountExceeded); // Use a ckeditor for the comments field
const isSaving = window.Vue.ref<boolean>(false);
const buttonTitle = window.Vue.computed(() => (isSaving.value ? "Saving..." : "Save and continue"));

/**
 * Save the comment
 */
async function saveComment() {
    const formData = new FormData();
    formData.append("comment", comment.value);
    await fetch(props.student.urls.attemptComment, {
        method: "POST",
        body: formData,
    });
}

/**
 * Update the response of the item
 */
async function onSaveSelected() {
    studentsStore.updateStudentFinalReview(props.student, comment.value);
    isSaving.value = true;
    await saveComment();
    isSaving.value = false;
}

/**
 * Save any unsaved changes in the front end only
 * Back end changes are saved via the fetch requests above
 */
window.Vue.onUnmounted(() => {
    studentsStore.updateStudentFinalReview(props.student, comment.value);
});
</script>

<template>
    <div>
        <div class="l-flex-col l-gap-m">
            <div>
                <label>Comment</label>
                <textarea :id="editorId"></textarea>
            </div>
            <div>
                <button class="full-width" :disabled="isSaving" @click.prevent="onSaveSelected">
                    {{ buttonTitle }}
                </button>
            </div>
        </div>
    </div>
</template>
