<script setup lang="ts">
/**
 * Contains a students response to an item
 */

import { slideUp, slideDown } from "@js/util/animateUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import StudentItemMark from "@schoolboxLearnosity/vue/student/student-item-mark.vue";
import StudentNoScoreNote from "@schoolboxLearnosity/vue/student/student-no-score-note.vue";
import StudentItemQuestionDisplay from "@schoolboxLearnosity/vue/student/student-item-question-display.vue";
import StudentItemComment from "@schoolboxLearnosity/vue/student/student-item-comment.vue";
import { useStore } from "../store/quizFeedbackStore";
import { useStudentStore } from "../store/qfStudentStore";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const store = useStore();
const studentStore = useStudentStore();
const itemEl = window.Vue.ref<HTMLElement | null>(null); // the element to render the item into

/**
 * Hide the item if it's already been marked and the user has chosen to only show unmarked items
 */
window.Vue.watch(
    () => props.student.showMarked,
    async (showMarked) => {
        if (showMarked && props.item.isMarked) {
            // reveal all
            await slideDown(itemEl.value as HTMLElement);
        } else if (!showMarked && props.item.isMarked) {
            // hide those that are marked
            await slideUp(itemEl.value as HTMLElement);
        }
    },
);

/**
 * To notify of a change in the score
 * @param question - the question to update
 * @param score - the score to save
 */
function onScoreUpdated(question: StudentItemQuestion, score: number): void {
    studentStore.updateItemResponseScore(props.item, question, score);
}

/**
 * To notify of a change in the comment
 * @param comment - the comment to save
 */
function onCommentUpdated(comment: string): void {
    studentStore.updateItemComment(props.item, props.item.questions[0], comment);
}
</script>

<template>
    <div ref="itemEl" class="c-island l-flex-col l-gap-m l-pad-m">
        <student-item-mark :item="item" :container="container as HTMLDivElement" :is-shown="true" :show-title="true" />
        <student-no-score-note v-if="!store.isReadOnly" :item="props.item" />
        <div class="item-questions l-flex-col l-gap-l">
            <student-item-question-display
                v-for="question in props.item.questions"
                :key="question.response_id"
                :student="props.student"
                :item="props.item"
                :question="question"
                :container="props.container"
                :is-read-only="store.isReadOnly"
                :show-feedback="props.student.isReviewed || !store.isReadOnly"
                :has-mark-visibility="store.hasMarkVisibility"
                @updated-score="onScoreUpdated"
            />
        </div>
        <student-item-comment
            v-if="props.student.isReviewed || !store.isReadOnly"
            :student="props.student"
            :item="props.item"
            :is-read-only="store.isReadOnly"
            :has-mark-visibility="store.hasMarkVisibility"
            @updated-comment="onCommentUpdated"
        />
    </div>
</template>

<style lang="scss">
.item-questions {
    .lrn_widget {
        margin: 0;
        padding: 0;
    }
}
</style>
