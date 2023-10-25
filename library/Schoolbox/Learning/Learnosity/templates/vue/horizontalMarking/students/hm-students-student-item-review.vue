<script setup lang="ts">
/**
 * To allow the teacher to review the item, to customise the score and add a comment
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
    item: StudentItem; //
}
const props = defineProps<Props>();
const studentsStore = useStudentsStore();
const editorId = `comment-${props.student.elementId}-${props.item.reference}`;
const isMaxCharCountExceeded = window.Vue.ref<boolean>(false);
const score = window.Vue.ref<number>(props.item.response.score ?? 0);
const comment = useEditor(props.item.response.comment ?? "", editorId, isMaxCharCountExceeded); // Use a ckeditor for the comments field
const isSaving = window.Vue.ref<boolean>(false);

const buttonTitle = window.Vue.computed(() => {
    if (isSaving.value) {
        return "Saving...";
    }
    return "Save and continue";
});

/**
 * Save the score
 */
async function saveScore() {
    const formData = new FormData();
    formData.append("learnosityReference", props.item.response.learnosityReference);
    formData.append("score", score.value.toString());
    await fetch(props.student.urls.score, {
        method: "POST",
        body: formData,
    });
}

/**
 * Save the comment
 */
async function saveComment() {
    const formData = new FormData();
    formData.append("learnosityReference", props.item.response.learnosityReference);
    formData.append("comment", comment.value);
    await fetch(props.student.urls.comment, {
        method: "POST",
        body: formData,
    });
}

/**
 * Save the score and comment, and then move to the next student
 */
async function onSaveSelected() {
    isSaving.value = true;
    await saveScore();
    await saveComment();
    isSaving.value = false;
    studentsStore.updateStudentReport(props.student, props.item, score.value, comment.value);
    // pass through the customised score and comment
    // can the -mark be updated? I assume so
    // once that's been submitted, move to the next student
    // if thats the last student, move to the next item
    // if thats the last student on the last item, move to finalise review
}
</script>

<template>
    <div>
        <label>Update Score (max score: {{ props.item.response.maxScore }})</label>
        <input
            :id="`customise-score-${$props.item.reference}`"
            v-model="score"
            type="number"
            min="0"
            :max="props.item.response.maxScore"
        />
    </div>
    <div>
        <label>Comment</label>
        <textarea :id="editorId"></textarea>
    </div>
    <div>
        <button class="full-width" :disabled="isSaving" @click.prevent="onSaveSelected">{{ buttonTitle }}</button>
    </div>
</template>
