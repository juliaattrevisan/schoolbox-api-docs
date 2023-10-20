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

/**
 * To display the item when it's the active item
 */
const display = window.Vue.computed(() => {
    return itemsStore.isFinalReview ? "block" : "none";
});

/**
 * Add in the editor
 */
window.Vue.onMounted(async () => {
    await useEditor("", editorId, isMaxCharCountExceeded); // Use a ckeditor for the comments field
});

/**
 * Update the response of the item
 */
function onSaveSelected() {
    studentsStore.updateStudentReport(props.student);
    // pass through the customised score and comment
    // can the -mark be updated? I assume so
    // once that's been submitted, move to the next student
    // if thats the last student, move to the next item
    // if thats the last student on the last item, move to finalise review
}
</script>

<template>
    <div class="item">
        <div class="l-flex-col l-gap-m">
            <div>
                <label>Final comment</label>
                <textarea :id="editorId"></textarea>
            </div>
            <div>
                <button class="full-width" @click.prevent="onSaveSelected">Save and continue</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.item {
    display: v-bind(display);
}
</style>
