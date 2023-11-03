<script setup lang="ts">
/**
 * Display a single student's responses
 */

import { gsap } from "gsap";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import HmStudentsStudentHeader from "./hm-students-student-header.vue";
import HmStudentsStudentItem from "./hm-students-student-item.vue";
import HmStudentsStudentAttemptComment from "./hm-students-student-attempt-comment.vue";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();

const studentEl = window.Vue.ref<HTMLDivElement | null>(null); // the entire student container
const studentInnerContainerEl = window.Vue.ref<HTMLDivElement | null>(null); // the entire student container
const responseEl = window.Vue.ref<HTMLDivElement | null>(null); // the response container
const responseInnerContainerEl = window.Vue.ref<HTMLDivElement | null>(null); // the response container
const responseContentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();
const isFirstMarkedAttemptsChange = window.Vue.ref(true);

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
    () => {
        // we only want to do this on the first change, as gsap doesn't know the value of grid-template-rows yet
        if (isFirstMarkedAttemptsChange.value) {
            isFirstMarkedAttemptsChange.value = false;
            studentEl.value?.style.setProperty("grid-template-rows", "1fr");
        }
        gsap.to(studentEl.value, {
            gridTemplateRows: props.student.isMarked && !studentsStore.showMarkedAttempts ? "0fr" : "1fr",
            duration: 0.3,
            onStart: () => {
                studentInnerContainerEl.value?.style.setProperty("overflow", "hidden");
            },
            onComplete: () => {
                // otherwise the ckeditor pop outs can be hidden
                if (studentsStore.showMarkedAttempts) {
                    studentInnerContainerEl.value?.style.setProperty("overflow", "visible");
                }
            },
        });
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
    () => {
        gsap.to(responseEl.value, {
            gridTemplateRows: props.student.isExpanded ? "1fr" : "0fr",
            duration: 0.3,
            onStart: () => {
                responseInnerContainerEl.value?.style.setProperty("overflow", "hidden");
            },
            onComplete: () => {
                // otherwise the ckeditor pop outs can be hidden
                if (props.student.isExpanded) {
                    responseInnerContainerEl.value?.style.setProperty("overflow", "visible");
                }
            },
        });
    }
);
</script>

<template>
    <div ref="studentEl" class="student l-grid">
        <div ref="studentInnerContainerEl" class="student__inner-container">
            <div ref="studentEl" class="c-island l-flex-col l-gap-m l-pad-m">
                <hm-students-student-header
                    :student="props.student"
                    :container="(responseContentEl as HTMLDivElement)"
                    @toggle="onToggleSelected"
                />
                <div ref="responseEl" class="response l-grid">
                    <div ref="responseInnerContainerEl" class="response__inner-container">
                        <div ref="responseContentEl" class="response__content">
                            <!-- this is the div Learnosity looks for to insert the response content into, it will be completely replaced -->
                            <div :id="props.student.elementId"></div>
                        </div>
                        <template v-if="props.student.isReportLoaded">
                            <hm-students-student-item
                                v-for="item in props.student.items"
                                :key="item.reference"
                                :student="props.student"
                                :item="item"
                                :container="(responseContentEl as HTMLDivElement)"
                            />
                            <hm-students-student-attempt-comment
                                v-if="itemsStore.isAttemptComment"
                                :student="props.student"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.student {
    &__inner-container {
        overflow: hidden;
    }
}

.response {
    &__inner-container {
        overflow: hidden;
    }
    &__content {
        // this contains the html from Learnosity, which has the response DOM elements that are extracted as we move through the items
        display: none;
    }
}
</style>
