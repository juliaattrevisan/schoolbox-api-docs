<script setup lang="ts">
/**
 * Display a single student's responses
 */

import StudentAttemptResponse from "@schoolboxLearnosity/vue/student/student-attempt-response.vue";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import QfStudentHeader from "./qf-student-header.vue";
import QfStudentItem from "./qf-student-item.vue";
import QfStudentShowMarked from "./qf-student-show-marked.vue";
import QfStudentAllMarked from "./qf-student-all-marked.vue";
import { useStore } from "../store/quizFeedbackStore";
import { useStudentStore } from "../store/qfStudentStore";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();
const store = useStore();

const responseContentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into

/**
 * To notify of a change in the comment
 * @param comment - the comment to save
 */
function onAttemptCommentUpdated(comment: string): void {
    useStudentStore().updateAttemptComment(props.student, comment);
}

/**
 * To notify of a change in the mark
 * @param mark - the mark to save
 */
function onAttemptMarkUpdated(mark: string): void {
    useStudentStore().updateAttemptMark(props.student, mark);
}
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <qf-student-header :student="props.student" />
        <div ref="responseContentEl" class="response-content c-island l-pad-m">
            <!-- this is the div Learnosity looks for to insert the response content into, it will be completely replaced -->
            <div :id="props.student.elementId"></div>
        </div>
        <div v-if="store.hasMarkVisibility && store.isReadOnly" class="l-flex-col l-gap-s">
            <h2 class="subheader f-no-margins">Summary</h2>
            <div id="schoolbox-completion-summary"></div>
        </div>
        <template v-if="props.student.isReportLoaded">
            <div class="l-flex-col l-gap-s">
                <h2 class="subheader f-no-margins">Items</h2>
                <qf-student-show-marked v-if="!store.isReadOnly" />
                <div class="l-flex-col l-gap-l">
                    <qf-student-item
                        v-for="item in props.student.items"
                        :key="item.reference"
                        :student="props.student"
                        :item="item"
                        :container="responseContentEl as HTMLDivElement"
                    />
                    <qf-student-all-marked />
                </div>
            </div>
            <div
                v-if="store.hasMarkVisibility && (props.student.isReviewed || !store.isReadOnly)"
                class="l-flex-col l-gap-s"
            >
                <h2 class="subheader f-no-margins">Overall Feedback</h2>
                <student-attempt-response
                    :student="props.student"
                    :is-read-only="store.isReadOnly"
                    :mark-data="store.markData"
                    @updated-comment="onAttemptCommentUpdated"
                    @updated-mark="onAttemptMarkUpdated"
                />
            </div>
        </template>
    </div>
</template>

<style scoped>
.response-content {
    /* this contains the html from Learnosity, which has the response DOM elements that are extracted as we move through the items */
    display: none;
}
</style>
