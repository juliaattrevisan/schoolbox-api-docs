<script setup lang="ts">
/**
 * To provide the opportunity for a final comment
 */

import debounce from "lodash/debounce";
import { timeout } from "@js/util/jsUtils";
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import { useStudentStore } from "../store/qfStudentStore";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();
const studentStore = useStudentStore();
const editorId = `final-review-comment-${props.student.elementId}`;
const comment = useEditor(props.student.attemptComment.comment ?? "", editorId, {
    targetElements: [`#${editorId}`],
    hasTokens: true,
}); // Use a ckeditor for the comments field
const isSaving = window.Vue.ref(false);
const isSavedDelay = window.Vue.ref(false);

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
const saveText = window.Vue.computed(() => {
    if (isSaving.value) {
        return "Saving...";
    }
    return "Comment saved";
});

/**
 * To open and close the save status
 * @returns {string} - the grid-template-rows
 */
const saveContainerHeight = window.Vue.computed(() => {
    if (isSaving.value || isSavedDelay.value) {
        return "1fr";
    }
    return "0fr";
});

/**
 * Save the comment if it's changed
 */
async function saveComment() {
    if (comment.value === props.student.attemptComment.comment) {
        return;
    }
    isSaving.value = true;
    const formData = new FormData();
    formData.append("comment", comment.value);
    await fetch(props.student.urls.attemptComment, {
        method: "POST",
        body: formData,
    });
    studentStore.updateAttemptComment(props.student, comment.value);
    isSavedDelay.value = true;
    isSaving.value = false;
    await timeout(2000);
    isSavedDelay.value = false;
}

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSave = debounce(() => {
    void saveComment();
}, 1000);

/**
 * save the comment when it's changed
 */
window.Vue.watch(comment, () => {
    debounceSave();
});

/**
 * Save any unsaved changes
 */
window.Vue.onUnmounted(async () => {
    await saveComment();
});
</script>

<template>
    <div>
        <div class="c-island l-flex-col l-gap-m l-pad-m">
            <div>
                <label>Comment</label>
                <p class="meta">
                    Student detail tokens can be viewed when you press [. Tokens will be substituted with the students
                    details on creation.
                </p>
                <textarea :id="editorId"></textarea>
                <div class="save-container l-grid">
                    <div class="save-container__inner-container">
                        <div :class="`alert-box f-no-margins ${isSavedDelay ? 'success' : ''}`">
                            <em>{{ saveText }}</em>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.save-container {
    grid-template-rows: v-bind(saveContainerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}
</style>
