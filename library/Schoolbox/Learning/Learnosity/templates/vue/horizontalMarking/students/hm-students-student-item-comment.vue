<script setup lang="ts">
/**
 * To allow the teacher to review the item, to customise the score and add a comment
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { EditorEvent } from "@js/editor/event/editorEvent";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item to display
}
const props = defineProps<Props>();
const studentsStore = useStudentsStore();
const editorId = `comment-${props.student.elementId}-${props.item.reference}`;
const comment = useEditor(props.item.questions[0].response.comment ?? "", editorId, {
    targetElements: [`#${editorId}`],
    hasTokens: true,
}); // Use a ckeditor for the comments field

/**
 * Save the comment if it's changed
 */
async function saveComment() {
    if (comment.value === props.item.questions[0].response.comment) {
        return;
    }
    studentsStore.updateItemComment(props.studen, props.item, props.item.questions[0], comment.value);
    const formData = new FormData();
    formData.append("learnosityReference", props.item.questions[0].response.learnosityReference);
    formData.append("comment", comment.value);
    await fetch(props.student.urls.comment, {
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
        editor.ui.focusTracker.on("change:isFocused", async (event, name, isFocused) => {
            if (!isFocused && comment.value !== props.item.questions[0].response.comment) {
                await saveComment();
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

// if there's no max score, the question is considered marked when a comment is added to the item
// this applies to any question within an item with no max score
</script>

<template>
    <div>
        <label>Comment</label>
        <p class="meta">
            Student detail tokens can be viewed when you press [. Tokens will be substituted with the students details
            on creation.
        </p>
        <textarea :id="editorId"></textarea>
    </div>
</template>
