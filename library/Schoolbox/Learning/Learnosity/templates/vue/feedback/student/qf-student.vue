<script setup lang="ts">
/**
 * Display a single student's responses
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import QfStudentHeader from "./qf-student-header.vue";
import QfStudentItem from "./qf-student-item.vue";
import QfStudentShowMarked from "./qf-student-show-marked.vue";
import QfStudentAllMarked from "./qf-student-all-marked.vue";
import QfStudentAttemptComment from "./qf-student-attempt-comment.vue";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();

const responseContentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <qf-student-header :student="props.student" :container="(responseContentEl as HTMLDivElement)" />
        <div ref="responseContentEl" class="response-content">
            <!-- this is the div Learnosity looks for to insert the response content into, it will be completely replaced -->
            <div :id="props.student.elementId"></div>
        </div>
        <div v-if="props.student.isReportLoaded" class="l-flex-col l-gap-m">
            <div class="l-flex-col l-gap-s">
                <h2 class="subheader">Cards</h2>
                <qf-student-show-marked />
                <div class="l-flex-col l-gap-l">
                    <qf-student-item
                        v-for="item in props.student.items"
                        :key="item.reference"
                        :student="props.student"
                        :item="item"
                        :container="(responseContentEl as HTMLDivElement)"
                    />
                    <qf-student-all-marked />
                </div>
            </div>
            <div class="l-flex-col l-gap-s">
                <h2 class="subheader">Overall Comment</h2>
                <qf-student-attempt-comment :student="props.student" />
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.response-content {
    // this contains the html from Learnosity, which has the response DOM elements that are extracted as we move through the items
    display: none;
}
</style>
