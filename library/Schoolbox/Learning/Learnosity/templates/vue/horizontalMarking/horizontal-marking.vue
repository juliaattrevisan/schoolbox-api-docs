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
import HmCardsHeader from "./hm-cards-header.vue";
import HmStudents from "./students/hm-students.vue";
import HmPreviousItem from "./items/hm-previous-item.vue";
import HmNextItem from "./items/hm-next-item.vue";

interface Props {
    title: string; // the title of the quiz
    items: Item[]; // the items linked to quiz
    students: Student[]; // the students who have attempted the quiz
    itemResponses: StudentItemQuestionResponse[]; // all the item responses for the quiz, which can include multiple questions within the same item
    attemptComments: StudentAttemptComment[]; // all the overall attempt comments for the quiz
    urls: StudentUrls[]; // the urls to use for the students to update their scores and comments
    reportPayloads: string[]; // the report payloads to load from the Learnosity Reports API
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
    store.init(props.itemResponses, props.attemptComments, props.urls);
    itemsStore.init(props.items);
    useStudentsStore().init(props.students);
    await window.Vue.nextTick();
    loadReports(props.reportPayloads);
});
</script>

<template>
    <div></div>
    <div class="l-flex-col l-gap-m f-no-margins">
        <h1>Horizontal Marking for {{ props.title }}</h1>
        <!-- Title. But add the metadata etc. when you get up to that bit -->
        <!-- <div class="l-flex-row l-flex--justify-space"> -->

        <!-- <div class="text-right">
                <label class="inline-block">Hide marked attempts</label>
                <div class="long switch f-no-margins">
                    <input
                        id="show-only-enabled"
                        v-model="itemsStore.hideMarkedAttempts"
                        type="checkbox"
                        data-test-show-only-enabled
                    />
                    <label for="show-only-enabled">
                        <span>Hidden</span>
                        <span>Shown</span>
                    </label>
                </div>
            </div> -->
        <!-- </div> -->
        <template v-if="hasStudents">
            <hm-cards-header />
            <!-- hm-students has to be available before store is loaded as the Learnosity Report API's look for the student elements to put the reports in -->
            <hm-students />
            <div class="l-flex-row l-flex--no-wrap l-gap-m">
                <hm-previous-item />
                <hm-next-item />
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

<style scoped></style>
