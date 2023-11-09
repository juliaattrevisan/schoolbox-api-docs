<script setup lang="ts">
/**
 * To provide feedback on a individual students attempt at a quiz
 */

//  import type { Item } from "@schoolboxLearnosity/js/types/Item";
// import type { Student } from "@schoolboxLearnosity/js/types/Student";
// import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";
// import type { StudentAttemptComment } from "@schoolboxLearnosity/js/types/StudentAttemptComment";
// import type { StudentUrls } from "@schoolboxLearnosity/js/types/StudentUrls";
import { useStore } from "./store/quizFeedbackStore";
import { loadReport } from "./util/loadReport";
import QfStudent from "./student/qf-student.vue";

interface Props {
    title: string; // the title of the quiz
    student: object; // Student[] the student who has attempted the quiz
    itemResponses: object[]; // StudentItemQuestionResponse[] all the item responses for the quiz, which can include multiple questions within the same item
    attempt: object; // StudentAttemptComment[] all the overall attempt comments for the quiz
    urls: object; // StudentUrls[] the urls to use for the students to update their scores and comments
    reportInitOptions: object; // the report payloads to load from the Learnosity Reports API
    statusPage: string; // the status page to link to once user has finished marking
}
const props = defineProps<Props>();
const store = useStore();

/**
 * Initialise the stores, adding the students first, then the reports as
 * when the reports are loaded they look for the students in the store/DOM tree
 */
window.Vue.onMounted(async () => {
    store.init(props.statusPage);
    await window.Vue.nextTick(); // update the DOM to ensure the elements are available for the reports to be loaded into
    loadReport(props.reportInitOptions);
});
</script>

<template>
    <div class="l-flex-col l-gap-l f-no-margins">
        <!-- Title. But add the metadata etc. when you get up to that bit -->
        <h1>Feedback for {{ props.title }}</h1>
        <!-- hm-students has to be available before store is loaded as the Learnosity Report API's look for the student elements to put the reports in -->
        <qf-student />
    </div>
</template>

<style>
/* remove the bottom margin so the save status can butt up against the bottom of the editor */
.ck.ck-editor {
    margin-bottom: 0 !important;
}
</style>
