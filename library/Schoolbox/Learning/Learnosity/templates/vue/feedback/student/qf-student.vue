<script setup lang="ts">
/**
 * Display a single student's responses
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import { useStudentStore } from "../store/qfStudentStore";
import QfStudentHeader from "./qf-student-header.vue";
import QfStudentItem from "./qf-student-item.vue";
import QfStudentAttemptComment from "./qf-student-attempt-comment.vue";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();

const studentEl = window.Vue.ref<HTMLDivElement | null>(null); // the entire student container
const studentInnerContainerEl = window.Vue.ref<HTMLDivElement | null>(null); // the entire student container
const responseEl = window.Vue.ref<HTMLDivElement | null>(null); // the response container
const responseInnerContainerEl = window.Vue.ref<HTMLDivElement | null>(null); // the response container
const responseContentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const studentStore = useStudentStore();

/**
 * Display the student now the linked report has been loaded
 */
window.Vue.watch(
    () => props.student.isReportLoaded,
    async () => {
        studentStore.toggleStudent(props.student);
        // expand the student if it is marked
        await window.Vue.nextTick();
    }
);
</script>

<template>
    <div ref="studentEl" class="student l-grid">
        <div ref="studentInnerContainerEl" class="student__inner-container">
            <div ref="studentEl" class="c-island l-flex-col l-gap-m l-pad-m">
                <qf-student-header :student="props.student" :container="(responseContentEl as HTMLDivElement)" />
                <div ref="responseEl" class="response l-grid">
                    <div ref="responseInnerContainerEl" class="response__inner-container">
                        <div ref="responseContentEl" class="response__content">
                            <!-- this is the div Learnosity looks for to insert the response content into, it will be completely replaced -->
                            <div :id="props.student.elementId"></div>
                        </div>
                        <template v-if="props.student.isReportLoaded">
                            <div class="l-flex-col l-gap-l">
                                <qf-student-item
                                    v-for="item in props.student.items"
                                    :key="item.reference"
                                    :student="props.student"
                                    :item="item"
                                    :container="(responseContentEl as HTMLDivElement)"
                                />
                                <qf-student-attempt-comment :student="props.student" />
                            </div>
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
