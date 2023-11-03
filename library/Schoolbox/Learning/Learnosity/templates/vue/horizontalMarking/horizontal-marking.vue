<script setup lang="ts">
/**
 * To use horizontal marking when assessing a quiz
 */

import type { Item } from "@schoolboxLearnosity/js/types/Item";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";
import type { StudentAttemptComment } from "@schoolboxLearnosity/js/types/StudentAttemptComment";
import type { StudentUrls } from "@schoolboxLearnosity/js/types/StudentUrls";
import { useStore } from "./store/horizontalMarkingStore";
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
    attemptComments: StudentAttemptComment[]; // all the overall attempt comments for the quiz
    urls: StudentUrls[]; // the urls to use for the students to update their scores and comments
    reportPayloads: string[]; // the report payloads to load from the Learnosity Reports API
    statusPage: string; // the status page to link to once user has finished marking
}
const props = defineProps<Props>();
const store = useStore();
const itemsStore = useItemsStore();

const hasStudents = window.Vue.computed(() => {
    return props.students && Object.keys(props.students).length > 0;
});

/**
 * Initialise the stores, adding the students first, then the reports as
 * when the reports are loaded they look for the students in the store/DOM tree
 */
window.Vue.onMounted(async () => {
    store.init(props.statusPage);
    itemsStore.init(props.items);
    useStudentsStore().init(props.students, props.itemResponses, props.attemptComments, props.urls);
    await window.Vue.nextTick();
    loadReports(props.reportPayloads);
});
</script>

<template>
    <div class="l-flex-col l-gap-l f-no-margins">
        <!-- Title. But add the metadata etc. when you get up to that bit -->
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
                <i class="icon-submissions"></i>
                <p>No attempts have been submitted yet.</p>
            </div>
        </template>
    </div>
</template>

<style>
/* remove the bottom margin so the save status can butt up against the bottom of the editor */
.ck.ck-editor {
    margin-bottom: 0 !important;
}
</style>
