<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import type { Student } from "../types/Student";
import type { Item } from "../types/Item";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
    item: Item; //
    container: HTMLDivElement; // the parent container, to look for the relevant item
    index: number; // the index of the student in the list
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
const responseEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const editorId = `comment-${props.student.elementId}-${props.item.reference}`;
const isMaxCharCountExceeded = window.Vue.ref<boolean>(false);

/**
 * To display the item when it's the active item
 */
const display = window.Vue.computed(() => {
    if (itemsStore.isFinalReview) {
        return "none";
    }
    return props.item.reference === itemsStore.activeItem?.reference ? "block" : "none";
});

/**
 * Update the styles of the response element to match the rest of the page
 * @param {HTMLDivElement} response - the response element to update the styles of
 */
function updateResponseStyles(response: HTMLDivElement) {
    const rowEl = response.querySelector(".row") as HTMLDivElement;
    rowEl.className = "";
    const colEl = response.querySelector(".col-xs-12") as HTMLDivElement;
    colEl.className = "l-flex-col l-gap-m";
}

/**
 * Add any sample answers to the questions in the items
 * @param {HTMLDivElement} response - the response element to add the sample answers to
 */
function addSampleAnswers(response: HTMLDivElement) {
    props.item.questions.forEach((question) => {
        if (question.metadata.sample_answer) {
            const addHtml = `
                <label>Sample Answer:</label> <em>${question.metadata.sample_answer}</em>
            `;
            const sampleAnswerEl = document.createElement("div");
            sampleAnswerEl.innerHTML = addHtml;
            const questionEl = response.querySelector(
                `[data-lrn-widget-container="${question.response_id}"]`
            ) as HTMLDivElement;
            questionEl.appendChild(sampleAnswerEl);
        }
    });
}

/**
 * Add the response to the item to our element
 */
window.Vue.onMounted(async () => {
    const response = props.container.querySelector(`[data-reference="${props.item.reference}"]`) as HTMLDivElement;
    responseEl.value?.appendChild(response);
    updateResponseStyles(response);
    if (props.item.questions.length > 0) {
        await useEditor("", editorId, isMaxCharCountExceeded); // Use a ckeditor for the comments field
        addSampleAnswers(response);
    }
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
            <div ref="responseEl"></div>
            <template v-if="props.item.questions.length > 0">
                <div>
                    <label>Customise Score</label>
                    <input :id="`customise-score-${$props.item.reference}`" type="text" />
                </div>
                <div>
                    <label>Comment</label>
                    <textarea :id="editorId"></textarea>
                </div>
                <div>
                    <button class="full-width" @click.prevent="onSaveSelected">Save and continue</button>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.item {
    display: v-bind(display);
}
</style>
