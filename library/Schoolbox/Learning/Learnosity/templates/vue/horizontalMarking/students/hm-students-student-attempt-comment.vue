<script setup lang="ts">
/**
 * To provide the opportunity for a final comment
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { EditorEvent } from "@js/editor/event/editorEvent";
import type { Student } from "../types/Student";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();
const studentsStore = useStudentsStore();
const editorEl = window.Vue.ref<HTMLTextAreaElement | null>(null);
const editorId = `final-review-comment-${props.student.elementId}`;
const isMaxCharCountExceeded = window.Vue.ref<boolean>(false);
const comment = useEditor(props.student.attemptComment.comment ?? "", editorId, isMaxCharCountExceeded); // Use a ckeditor for the comments field

/**
 * Save the comment
 */
async function saveComment() {
    studentsStore.updateAttemptComment(props.student, comment.value);
    const formData = new FormData();
    formData.append("comment", comment.value);
    await fetch(props.student.urls.attemptComment, {
        method: "POST",
        body: formData,
    });
}

/**
 * Start listening for the editor to be created and then listen for focus changes to save the comment
 */
window.Vue.onMounted(() => {
    const ckeditorElement = document.getElementById(editorId) as HTMLElement;
    ckeditorElement.addEventListener(EditorEvent.EDITOR__CREATED, (event) => {
        const editor = event.detail.editor;
        editor.ui.focusTracker.on("change:isFocused", (event, name, isFocused) => {
            if (!isFocused) {
                saveComment();
            }
        });
    });
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
        <div class="l-flex-col l-gap-m">
            <div>
                <label>Comment</label>
                <textarea :id="editorId" ref="editorEl"></textarea>
            </div>
        </div>
    </div>
</template>
