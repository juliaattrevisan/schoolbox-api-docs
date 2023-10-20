<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import { insertElementAfter } from "@js/util/domUtils";
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import type { Item } from "../types/Item";
import { useItemsStore } from "../store/hmItemsStore";

interface Props {
    item: Item; //
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();
const responseEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const editorId = `comment-${props.item.reference}`;
const isMaxCharCountExceeded = window.Vue.ref<boolean>(false);

/**
 * To display the item when it's the active item
 */
const display = window.Vue.computed(() => {
    return props.item.reference === itemsStore.activeItem.reference ? "block" : "none";
});

/**
 * Use a ckeditor for the comments field
 */
useEditor("", editorId, isMaxCharCountExceeded);

/**
 * Add any sample answers to the questions in the items
 * @param {HTMLDivElement} response - the response element to add the sample answers to
 */
function addSampleAnwers(response: HTMLDivElement) {
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
            insertElementAfter(questionEl, sampleAnswerEl);
        }
    });
}

/**
 * Add the response to the item to our element
 */
window.Vue.onMounted(() => {
    const response = props.container.querySelector(`[data-reference="${props.item.reference}"]`) as HTMLDivElement;
    responseEl.value?.appendChild(response);
    addSampleAnwers(response);
});
</script>

<template>
    <div class="item">
        <div class="l-flex-col l-gap-s l-pad-s">
            <div ref="responseEl"></div>
            <div>
                <label>Customise Score</label>
                <input :id="`customise-score-${$props.item.reference}`" type="text" />
            </div>
            <div>
                <label>Comment</label>
                <textarea :id="editorId"></textarea>
            </div>
        </div>
    </div>
</template>

<style scoped>
.item {
    display: v-bind(display);
}
</style>
