<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import { useItemsStore } from "../store/hmItemsStore";
import hmStudentsStudentItemQuestion from "./hm-students-student-item-question.vue";
import hmStudentsStudentItemComment from "./hm-students-student-item-comment.vue";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();
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
</script>

<template>
    <transition>
        <div v-if="isActive">
            <div class="l-flex-col l-gap-m">
                <div v-if="hasQuestionsWithNoScore" class="alert-box f-no-margins">
                    Any questions within a card that don't have a score are considered marked when a comment is added to
                    the card.
                </div>
                <div class="item-questions l-flex-col l-gap-m">
                    <hm-students-student-item-question
                        v-for="question in props.item.questions"
                        :key="question.response_id"
                        :student="props.student"
                        :item="props.item"
                        :question="question"
                        :container="props.container"
                    />
                </div>
                <hm-students-student-item-comment :student="props.student" :item="props.item" />
            </div>
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
