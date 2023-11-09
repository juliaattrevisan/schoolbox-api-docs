<script setup lang="ts">
/**
 * To provide feedback on a individual students attempt at a quiz
 */

import { useStore } from "./store/quizFeedbackStore";
import { loadReport } from "./util/loadReport";

interface Props {
    title: string; // the title of the quiz
    itemResponses: object[]; // StudentItemQuestionResponse[] all the item responses for the quiz, which can include multiple questions within the same item
    attempt: object; // StudentAttemptComment[] all the overall attempt comments for the quiz
    // urls: StudentUrls[]; // the urls to use for the students to update their scores and comments
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
    // await window.Vue.nextTick(); // update the DOM to ensure the elements are available for the reports to be loaded into
    // loadReport(props.reportInitOptions);
});
</script>

<template>
    <div></div>
</template>

<style>
/* remove the bottom margin so the save status can butt up against the bottom of the editor */
.ck.ck-editor {
    margin-bottom: 0 !important;
}
</style>
