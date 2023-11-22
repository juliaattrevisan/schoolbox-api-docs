<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import StudentNoScoreNote from "@schoolboxLearnosity/vue/student/student-no-score-note.vue";
import StudentItemQuestionDisplay from "@schoolboxLearnosity/vue/student/student-item-question-display.vue";
import StudentItemComment from "@schoolboxLearnosity/vue/student/student-item-comment.vue";
import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    container: HTMLElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const store = useStore();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();

/**
 * To display the item when it's the active item
 * @returns {boolean} - whether the item is active
 */
const isActive = window.Vue.computed(() => {
    if (itemsStore.isAttemptComment) {
        return false;
    }
    return props.item.reference === itemsStore.activeItem?.reference;
});

/**
 * Need to prevent moving to another item while the score or comment is being saved
 */
function onUpdating() {
    store.isProcessing = true;
}

/**
 * To notify of a change in the score
 * @param {StudentItemQuestion} question - the question to update
 * @param {number} score - the score to save
 */
function onScoreUpdated(question: StudentItemQuestion, score: number) {
    studentsStore.updateItemResponseScore(props.student, props.item, question, score);
    store.isProcessing = false;
}

/**
 * To notify of a change in the comment
 * @param {string} comment - the comment to save
 */
function onCommentUpdated(comment: string) {
    studentsStore.updateItemComment(props.student, props.item, props.item.questions[0], comment);
    store.isProcessing = false;
}
</script>

<template>
    <transition>
        <div v-if="isActive" class="l-flex-col l-gap-m l-pad-m">
            <student-no-score-note :item="props.item" />
            <div class="item-questions l-flex-col l-gap-m">
                <student-item-question-display
                    v-for="question in props.item.questions"
                    :key="question.response_id"
                    :student="props.student"
                    :item="props.item"
                    :question="question"
                    :container="props.container"
                    :is-read-only="false"
                    @updating-score="onUpdating"
                    @updated-score="onScoreUpdated"
                />
            </div>
            <student-item-comment
                :student="props.student"
                :item="props.item"
                :is-read-only="false"
                @updating-comment="onUpdating"
                @updated-comment="onCommentUpdated"
            />
        </div>
    </transition>
</template>

<style lang="scss">
.item-questions {
    .lrn_widget {
        margin: 0;
        padding: 0;
    }
}

.v-enter-active {
    transition: opacity 0.3s ease-out;
}
.v-enter-from {
    opacity: 0;
}
</style>
