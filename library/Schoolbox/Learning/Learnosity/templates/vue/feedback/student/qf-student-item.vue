<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import { slideUp, slideDown } from "@js/util/animateUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import StudentItemComment from "@schoolboxLearnosity/vue/student/student-item-comment.vue";
import StudentItemMark from "@schoolboxLearnosity/vue/student/student-item-mark.vue";
import StudentItemQuestionDisplay from "@schoolboxLearnosity/vue/student/student-item-question-display.vue";
import { useStudentStore } from "../store/qfStudentStore";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemEl = window.Vue.ref<HTMLElement | null>(null); // the element to render the item into
const hasQuestionsWithNoScore = props.item.questions.some((question) => question.response.maxScore === null);
const studentStore = useStudentStore();

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
    }
);

/**
 * To notify of a change in the score
 * @param {StudentItemQuestion} question - the question to update
 * @param {number} score - the score to save
 */
function onScoreUpdated(question: StudentItemQuestion, score: number) {
    studentStore.updateItemResponseScore(props.student, props.item, question, score);
}

/**
 * To notify of a change in the comment
 * @param {string} comment - the comment to save
 */
function onCommentUpdated(comment: string) {
    studentStore.updateItemComment(props.student, props.item, props.item.questions[0], comment);
}
</script>

<template>
    <div ref="itemEl" class="c-island l-flex-col l-gap-m l-pad-m">
        <student-item-mark :item="item" :container="(container as HTMLDivElement)" />
        <div v-if="hasQuestionsWithNoScore">
            <div class="alert-box f-no-margins">
                Any questions within a card that don't have a score are considered marked when a comment is added to the
                card.
            </div>
        </div>
        <div class="item-questions l-flex-col l-gap-l">
            <student-item-question-display
                v-for="question in props.item.questions"
                :key="question.response_id"
                :student="props.student"
                :item="props.item"
                :question="question"
                :container="props.container"
                @update-score="onScoreUpdated"
            />
        </div>
        <student-item-comment :student="props.student" :item="props.item" @update-comment="onCommentUpdated" />
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
