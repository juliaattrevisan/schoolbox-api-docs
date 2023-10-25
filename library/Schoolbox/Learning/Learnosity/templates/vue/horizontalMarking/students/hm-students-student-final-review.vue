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
    // formData.append("learnosityReference", props.item.response.learnosityReference);
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
    isSaving.value = true;
    await saveComment();
    isSaving.value = false;
    // studentsStore.updateStudentReport(props.student, props.item, score.value, comment.value);
    // pass through the customised score and comment
    // can the -mark be updated? I assume so
    // once that's been submitted, move to the next student
    // if that's the last student, move to the next item
    // if that's the last student on the last item, move to finalise review
}
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
