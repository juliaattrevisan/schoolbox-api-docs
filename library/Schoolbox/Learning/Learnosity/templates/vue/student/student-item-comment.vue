<script setup lang="ts">
/**
 * To add or update the comment of an item
 * Note that any questions without a score are considered marked when a comment is added to the item
 * The comment is linked to the first question in the item.
 * So the first comment is considered the comment for the entire item regardless of how many questions are in the item
 */

import { watchDebounced } from "@vueuse/core";
import { timeout } from "@js/util/jsUtils";
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item to display
    isReadOnly: boolean; // whether the question is readonly, we don't need to init the ckeditor if it's readonly
}
const props = defineProps<Props>();
const editorId = `comment-${props.student.elementId}-${props.item.reference}`;
const comment = props.isReadOnly
    ? window.Vue.ref("")
    : useEditor(props.item.questions[0].response?.comment ?? "", editorId, {
          targetElements: [`#${editorId}`],
          hasTokens: true,
      }); // Use a ckeditor for the comments field
const isSaving = window.Vue.ref(false);
const isSavedDelay = window.Vue.ref(false);

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
 * To notify the user that they're leaving the page while data is being saved
 * @param {BeforeUnloadEvent} event - the event to prevent
 */
function beforeUnloadHandler(event: BeforeUnloadEvent) {
    event.preventDefault();
    event.returnValue = "";
}

/**
 * Save the comment if it's changed
 */
async function saveComment() {
    if (props.item.questions[0].response === undefined) {
        return;
    }
    if (isSaving.value) {
        return;
    }
    if (comment.value === props.item.questions[0].response?.comment) {
        return;
    }
    if (props.student.urls === undefined) {
        return;
    }
    isSaving.value = true;
    emit("updatedComment", comment.value);
    window.addEventListener("beforeunload", beforeUnloadHandler);
    const formData = new FormData();
    formData.append("learnosityReference", props.item.questions[0].response.learnosityReference);
    formData.append("comment", comment.value);
    await fetch(props.student.urls.comment, {
        method: "POST",
        body: formData,
    });
    window.removeEventListener("beforeunload", beforeUnloadHandler);
    isSavedDelay.value = true;
    isSaving.value = false;
    await timeout(2000);
    isSavedDelay.value = false;
}

/**
 * save the comment when it's changed
 */
watchDebounced(
    comment,
    () => {
        void saveComment();
    },
    { debounce: 1000 }
);

/**
 * Save any unsaved changes
 */
window.Vue.onUnmounted(async () => {
    await saveComment();
});
</script>

<template>
    <div v-if="props.isReadOnly && props.item.questions[0].response?.comment !== undefined && props.item.questions[0].response?.comment !== ''">
        <label>Comment</label>
        <div v-html="props.item.questions[0].response.comment"></div>
    </div>
    <template v-else-if="!props.isReadOnly">
        <p class="meta">
            Student detail tokens can be viewed when you press [. Tokens will be substituted with the students details on creation.
        </p>
        <textarea :id="editorId"></textarea>
        <div class="save-container l-grid">
            <div class="save-container__inner-container">
                <div :class="`alert-box f-no-margins ${isSavedDelay ? 'success' : ''}`">
                    <em>{{ saveText }}</em>
                </div>
            </div>
        </div>
    </template>
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
