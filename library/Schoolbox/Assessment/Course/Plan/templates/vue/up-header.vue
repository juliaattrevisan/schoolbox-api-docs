<script setup lang="ts">
/**
 * The Unit planner header, containing the title
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";

interface Props {
    courseTitle: string;
    courseId: number;
    folderId: number;
    routeCurriculumCoverage: string | null;
}
const props = defineProps<Props>();

/**
 * To show the print option under three dot menu
 */
function onPrintSelected(): void {
    window.print();
}
</script>

<template>
    <div class="row actions-small-1 actions-medium-2">
        <div class="button-action small-12 columns">
            <h1>Unit Planner - {{ props.courseTitle }}</h1>
            <sbx-pop-out content-id="unit-dropdown"></sbx-pop-out>
        </div>
    </div>
    <ul id="unit-dropdown" class="f-dropdown" data-dropdown-content aria-hidden="true">
        <li>
            <a :href="`/course/modify/${props.courseId}?${getReturnUrl()}`" class="icon-modify">Modify Course</a>
        </li>
        <li>
            <a :href="`/homepage/${props.folderId}`" class="icon-home">View Homepage</a>
        </li>
        <li>
            <a :href="`/learning/courseMarkbook/${props.courseId}`" class="icon-curriculum">Course Markbook</a>
        </li>
        <li v-if="routeCurriculumCoverage">
            <a :href="`${props.routeCurriculumCoverage}?courseId=${props.courseId}`" class="icon-report"
                >Curriculum Coverage</a
            >
        </li>
        <li>
            <a :href="`/learning/assessment/calendar?course=${props.courseId}`" class="icon-calendar"
                >Assessment Calendar</a
            >
        </li>
        <li>
            <a href="#" class="icon-print" @click.prevent="onPrintSelected">Print Unit Planner</a>
        </li>
    </ul>
</template>

<style scoped>
/* Styles for printing */
@media print {
    ul#unit-dropdown {
        display: none !important;
    }
}
</style>
