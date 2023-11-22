<script setup lang="ts">
/**
 * Display a single student's responses
 */

import StudentAttemptComment from "@schoolboxLearnosity/vue/student/student-attempt-comment.vue";
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
 * Need to prevent moving to another item while the comment is being saved
 */
function onAttemptCommentUpdating() {
    store.isProcessing = true;
}

/**
 * To notify of a change in the comment
 * @param {string} comment - the comment to save
 */
function onAttemptCommentUpdated(comment: string) {
    useStudentStore().updateAttemptComment(props.student, comment);
    store.isProcessing = false;
}
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <qf-student-header :student="props.student" />
        <div ref="responseContentEl" class="response-content">
            <!-- this is the div Learnosity looks for to insert the response content into, it will be completely replaced -->
            <div :id="props.student.elementId"></div>
        </div>
        <template v-if="props.student.isReportLoaded">
            <div class="l-flex-col l-gap-s">
                <h2 class="subheader f-no-margins">Items</h2>
                <qf-student-show-marked />
                <div class="l-flex-col l-gap-l">
                    <qf-student-item
                        v-for="item in props.student.items"
                        :key="item.reference"
                        :student="props.student"
                        :item="item"
                        :container="(responseContentEl as HTMLDivElement)"
                    />
                    <qf-student-all-marked />
                </div>
            </div>
            <div class="l-flex-col l-gap-s">
                <h2 class="subheader f-no-margins">Overall Comment</h2>
                <student-attempt-comment
                    :student="props.student"
                    @updating-comment="onAttemptCommentUpdating"
                    @updated-comment="onAttemptCommentUpdated"
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
