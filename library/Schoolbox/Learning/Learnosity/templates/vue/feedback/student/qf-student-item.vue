<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import { slideUp, slideDown } from "@js/util/animateUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import StudentItemMark from "@schoolboxLearnosity/vue/student/student-item-mark.vue";
import QfStudentItemQuestion from "./qf-student-item-question.vue";
import QfStudentItemComment from "./qf-student-item-comment.vue";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemEl = window.Vue.ref<HTMLElement | null>(null); // the element to render the item into
const hasQuestionsWithNoScore = props.item.questions.some((question) => question.response.maxScore === null);

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
.item-questions {
    .lrn_widget {
        margin: 0;
        padding: 0;
    }
}
</style>
