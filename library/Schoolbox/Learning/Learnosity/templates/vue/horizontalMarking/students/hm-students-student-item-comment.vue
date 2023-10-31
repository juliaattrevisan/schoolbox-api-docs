<script setup lang="ts">
/**
 * To allow the teacher to review the item, to customise the score and add a comment
 */

// import { useFocus } from "@vueuse/core";
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { EditorEvent } from "@js/editor/event/editorEvent";
import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item to display
}
const props = defineProps<Props>();
const studentsStore = useStudentsStore();
const editorId = `comment-${props.student.elementId}-${props.item.reference}`;
const isMaxCharCountExceeded = window.Vue.ref<boolean>(false);
const comment = useEditor(props.item.questions[0].response.comment ?? "", editorId, isMaxCharCountExceeded); // Use a ckeditor for the comments field

/**
 * Save the comment
 */
async function saveComment() {
    studentsStore.updateItemComment(props.item.questions[0], comment.value);
    const formData = new FormData();
    formData.append("learnosityReference", props.item.questions[0].response.learnosityReference);
    formData.append("comment", comment.value);
    await fetch(props.student.urls.comment, {
        method: "POST",
        body: formData,
    });
}

/**
 *
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
        <label>Comment</label>
        <textarea :id="editorId"></textarea>
    </div>
</template>
