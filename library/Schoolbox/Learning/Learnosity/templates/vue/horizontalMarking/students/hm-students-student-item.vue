<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import StudentItemQuestionDisplay from "@schoolboxLearnosity/vue/student/student-item-question-display.vue";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import hmStudentsStudentItemComment from "./hm-students-student-item-comment.vue";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
const hasQuestionsWithNoScore = props.item.questions.some((question) => question.response.maxScore === null);

/**
 * To display the item when it's the active item
 */
const isActive = window.Vue.computed(() => {
    if (itemsStore.isAttemptComment) {
        return false;
    }
    return props.item.reference === itemsStore.activeItem?.reference;
});

/**
 * To notify of a change in the score
 * @param {StudentItemQuestion} question - the question to update
 * @param {number} score - the score to save
 */
function onScoreUpdated(question: StudentItemQuestion, score: number) {
    studentsStore.updateItemResponseScore(props.student, props.item, question, score);
}
</script>

<template>
    <transition>
        <div v-if="isActive" class="l-flex-col l-gap-m">
            <div v-if="hasQuestionsWithNoScore">
                <div class="alert-box f-no-margins">
                    Any questions within a card that don't have a score are considered marked when a comment is added to
                    the card.
                </div>
            </div>
            <div class="item-questions l-flex-col l-gap-m">
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
            <hm-students-student-item-comment :student="props.student" :item="props.item" />
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
    transition: opacity 0.3s ease;
}
.v-enter-from {
    opacity: 0;
}
</style>
