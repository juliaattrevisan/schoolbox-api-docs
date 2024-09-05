<script setup lang="ts">
/**
 * To provide the opportunity for a final response
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";
import StudentAttemptResponseMark from "./student-attempt-response-mark.vue";
import StudentAttemptResponseComment from "./student-attempt-response-comment.vue";

interface Props {
    student: Student; // the student to display
    isReadOnly: boolean; // whether the question is readonly, we don't need to init the ckeditor if it's readonly
    markData: AssessmentMarkData; // the mark type data linked to the assessment
}
const props = defineProps<Props>();

const emit = defineEmits<{
    updatedMark: [mark: string];
    updatedComment: [comment: string];
}>();

/**
 * To notify of a change in the mark
 * @param mark - the mark to save
 */
function onAttemptMarkUpdated(mark: string): void {
    emit("updatedMark", mark);
}

/**
 * To notify of a change in the comment
 * @param comment - the comment to save
 */
function onAttemptCommentUpdated(comment: string): void {
    emit("updatedComment", comment);
}
</script>

<template>
    <div class="c-island l-pad-m">
        <div class="l-flex-row l-gap-m">
            <student-attempt-response-mark
                :student="props.student"
                :is-read-only="props.isReadOnly"
                :mark-data="props.markData"
                @updated-mark="onAttemptMarkUpdated"
            />
            <student-attempt-response-comment
                :student="props.student"
                :is-read-only="props.isReadOnly"
                @updated-comment="onAttemptCommentUpdated"
            />
        </div>
    </div>
</template>

<style lang="scss" scoped></style>
