<script setup lang="ts">
/**
 * Display a single student's responses
 */

import { slideUp, slideDown } from "@js/util/animateUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import StudentAttemptComment from "@schoolboxLearnosity/vue/student/student-attempt-comment.vue";
import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import HmStudentsStudentHeader from "./hm-students-student-header.vue";
import HmStudentsStudentItem from "./hm-students-student-item.vue";
import HmStudentsStudentUnavailableItem from "./hm-students-student-unavailable-item.vue";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();

const studentEl = window.Vue.ref<HTMLElement | null>(null); // the entire student container
const responseEl = window.Vue.ref<HTMLElement | null>(null); // the response container
const responseContentEl = window.Vue.ref<HTMLElement | null>(null); // the element to render the report into
const store = useStore();
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();

/**
 * To show/hide the response when selected
 */
function onToggleSelected() {
    studentsStore.toggleStudent(props.student);
}

/**
 * To show/hide the entire student container, hiding if the student has been marked
 * and the option to show marked attempts is unchecked
 */
window.Vue.watch(
    () => studentsStore.showMarkedAttempts,
    async (showMarkedAttempts) => {
        if (showMarkedAttempts && props.student.isMarked) {
            await slideDown(studentEl.value as HTMLElement);
        } else if (!showMarkedAttempts && props.student.isMarked) {
            await slideUp(studentEl.value as HTMLElement);
        }
    }
);

/**
 * Display the student now the linked report has been loaded
 */
window.Vue.watch(
    () => props.student.isReportLoaded,
    async () => {
        onToggleSelected();
        await window.Vue.nextTick();
    }
);

/**
 * To show/hide the response container
 */
window.Vue.watch(
    () => props.student.isExpanded,
    async (isExpanded) => {
        if (isExpanded) {
            await slideDown(responseEl.value as HTMLElement);
        } else {
            await slideUp(responseEl.value as HTMLElement);
        }
    }
);

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
    studentsStore.updateAttemptComment(props.student, comment);
    store.isProcessing = false;
}
</script>

<template>
    <div ref="studentEl" class="student c-island l-flex-col l-gap-m">
        <hm-students-student-header
            :student="props.student"
            :container="(responseContentEl as HTMLElement)"
            @toggle="onToggleSelected"
        />
        <div ref="responseEl" class="">
            <div ref="responseContentEl" class="response-content">
                <!-- this is the div Learnosity looks for to insert the response content into, it will be completely replaced -->
                <div :id="props.student.elementId"></div>
            </div>
            <template v-if="props.student.isReportLoaded">
                <hm-students-student-item
                    v-for="item in props.student.items"
                    :key="item.reference"
                    :student="props.student"
                    :item="item"
                    :container="(responseContentEl as HTMLElement)"
                />
                <student-attempt-comment
                    v-if="itemsStore.isAttemptComment"
                    :student="props.student"
                    :is-read-only="false"
                    @updating-comment="onAttemptCommentUpdating"
                    @updated-comment="onAttemptCommentUpdated"
                />
                <hm-students-student-unavailable-item :student="props.student" />
            </template>
        </div>
    </div>
</template>

<style scoped>
.response-content {
    /* this contains the html from Learnosity, which has the response DOM elements that are extracted as we move through the items */
    display: none;
}
</style>
