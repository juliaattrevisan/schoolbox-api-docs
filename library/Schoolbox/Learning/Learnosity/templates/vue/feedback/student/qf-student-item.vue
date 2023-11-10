<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import QfStudentItemMark from "./qf-student-item-mark.vue";
import QfStudentItemQuestion from "./qf-student-item-question.vue";
import QfStudentItemComment from "./qf-student-item-comment.vue";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const hasQuestionsWithNoScore = props.item.questions.some((question) => question.response.maxScore === null);
</script>

<template>
    <div class="l-flex-col l-gap-m l-pad-l--top item">
        <qf-student-item-mark :item="item" :container="(container as HTMLDivElement)" />
        <div v-if="hasQuestionsWithNoScore">
            <div class="alert-box f-no-margins">
                Any questions within a card that don't have a score are considered marked when a comment is added to the
                card.
            </div>
        </div>
        <div class="item-questions l-flex-col l-gap-l">
            <qf-student-item-question
                v-for="question in props.item.questions"
                :key="question.response_id"
                :student="props.student"
                :item="props.item"
                :question="question"
                :container="props.container"
            />
        </div>
        <qf-student-item-comment :student="props.student" :item="props.item" />
    </div>
</template>

<style lang="scss">
.item {
    border-top: 4px solid var(--body-background);
}

.item-questions {
    .lrn_widget {
        margin: 0;
        padding: 0;
    }
}
</style>
