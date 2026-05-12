<script setup lang="ts">
/**
 * To use horizontal marking when assessing a quiz
 */

import type { Item } from "@schoolboxLearnosity/js/types/Item";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";
import type { StudentAttemptResponse } from "@schoolboxLearnosity/js/types/StudentAttemptResponse";
import type { StudentUrls } from "@schoolboxLearnosity/js/types/StudentUrls";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";
import type { Template } from "@js/editor/types/editorTypes";
import { useStore } from "./store/horizontalMarkingStore";
import { useStudentItemStore } from "../student/store/studentItemStore";
import { useItemsStore } from "./store/hmItemsStore";
import { useStudentsStore } from "./store/hmStudentsStore";
import { loadReports } from "./util/loadReports";
import HmItemsHeader from "./items/hm-items-header.vue";
import HmStudents from "./students/hm-students.vue";
import HmPreviousItem from "./items/hm-previous-item.vue";
import HmNextItem from "./items/hm-next-item.vue";
import HmFinaliseReview from "./items/hm-finalise-review.vue";

interface Props {
    title: string; // the title of the quiz
    items: Item[]; // the items linked to quiz
    students: Student[]; // the students who have attempted the quiz
    itemResponses: StudentItemQuestionResponse[]; // all the item responses for the quiz, which can include multiple questions within the same item
    attemptResponses: StudentAttemptResponse[]; // all the overall attempt comments for the quiz
    urls: StudentUrls[]; // the urls to use for the students to update their scores and comments
    reportPayloads: string[]; // the report payloads to load from the Learnosity Reports API
    markData: AssessmentMarkData; // the mark type data linked to the assessment
    statusPage: string; // the status page to link to once user has finished marking
    editorTemplates: Template[];
}
const props = defineProps<Props>();
const store = useStore();
const studentItemStore = useStudentItemStore();
const itemsStore = useItemsStore();

/**
 * To check if there are any students, otherwise display an empty state
 * @returns {boolean} - true if there are students
 */
const hasStudents = window.Vue.computed(() => {
    return props.students && Object.keys(props.students).length > 0;
});

/**
 * Initialise the stores, adding the students first, then the reports as
 * when the reports are loaded they look for the students in the store/DOM tree
 */
window.Vue.onMounted(async () => {
    store.init(props.markData, props.statusPage);
    studentItemStore.init(props.editorTemplates);
    itemsStore.init(props.items);
    useStudentsStore().init(props.students, props.itemResponses, props.attemptResponses, props.urls);
    await window.Vue.nextTick(); // update the DOM to ensure the elements are available for the reports to be loaded into
    loadReports(props.reportPayloads);
});
</script>

<template>
    <div class="l-flex-col l-gap-l l-pad-m f-no-margins">
        <h1>Horizontal Marking for {{ props.title }}</h1>
        <template v-if="hasStudents">
            <hm-items-header />
            <!-- hm-students has to be available before store is loaded as the Learnosity Report API's look for the student elements to put the reports in -->
            <hm-students />
            <div class="l-flex-row l-flex--no-wrap l-gap-m">
                <hm-previous-item />
                <hm-next-item />
                <hm-finalise-review />
            </div>
        </template>
        <template v-else>
            <div class="empty-state-flex-centered">
                <sbx-icon name="quiz" size="2.5rem"></sbx-icon>
                <p>No attempts have been submitted yet.</p>
            </div>
        </template>
    </div>
</template>

<style>
/* hide the features container */
.lrn_float_element_container {
    display: none;
}
</style>
