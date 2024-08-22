<script setup lang="ts">
/**
 * To provide feedback on a individual students attempt at a quiz
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";
import type { StudentAttemptResponse } from "@schoolboxLearnosity/js/types/StudentAttemptResponse";
import type { StudentUrls } from "@schoolboxLearnosity/js/types/StudentUrls";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";
import type { LearnosityReportInitOptions } from "@schoolboxLearnosity/vue/learnosityReportTypes";
import { useStore } from "./store/quizFeedbackStore";
import { useStudentStore } from "./store/qfStudentStore";
import { loadReport } from "./util/loadReport";
import QfStudent from "./student/qf-student.vue";
import QfFinaliseReview from "./qf-finalise-review.vue";

interface Props {
    student: Student; // the student who has attempted the quiz
    reportInitOptions: LearnosityReportInitOptions; // the report payloads to load from the Learnosity Reports API
    itemResponses: StudentItemQuestionResponse[]; // all the item responses for the quiz, which can include multiple questions within the same item
    attemptResponse: StudentAttemptResponse; // StudentAttemptResponse all the overall attempt comments for the quiz
    isReadOnly: boolean; // whether is in feedback mode, where the teacher can provide feedback to the student
    isReviewed: boolean; // whether the teacher has reviewed the attempt
    hasMarkVisibility: boolean; // whether marks and feedback are visible to the user
    markData: AssessmentMarkData; // the mark type data linked to the assessment
    urls?: StudentUrls; // the urls to use for the students to update their scores and comments
    statusPage?: string; // the status page to link to once user has finished marking
}

const props = defineProps<Props>();
const store = useStore();
const studentStore = useStudentStore();

/**
 * Initialise the stores, adding the students first, then the reports as
 * when the reports are loaded they look for the students in the store/DOM tree
 */
window.Vue.onMounted(async () => {
    store.init(props.isReadOnly, props.hasMarkVisibility, props.markData, props.statusPage);
    useStudentStore().init(props.student, props.itemResponses, props.attemptResponse, props.urls, props.isReviewed);
    await window.Vue.nextTick(); // update the DOM to ensure the elements are available for the reports to be loaded into
    loadReport(props.reportInitOptions, props.hasMarkVisibility);
});
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <qf-student :student="studentStore.student" />
        <qf-finalise-review v-if="!props.isReadOnly && studentStore.student.isReportLoaded" />
    </div>
</template>

<style>
/* remove the bottom margin so the save status can butt up against the bottom of the editor */
.ck.ck-editor {
    margin-bottom: 0 !important;
}

/* hide the features container */
.lrn_float_element_container {
    display: none;
}
</style>
