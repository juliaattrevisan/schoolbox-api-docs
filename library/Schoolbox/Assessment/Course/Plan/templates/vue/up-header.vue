<script setup lang="ts">
/**
 * The Unit planner header, containing the title
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";
import locale from "@js/terminology/rosettaStone";
import { useAddUnitsStore } from "./store/unitPlannerAddUnitsStore";

interface Props {
    courseTitle: string;
    courseId: number;
    folderId: number;
    routeCurriculumCoverage: string | null;
    routeImportIntoClasses: string | null;
    routeNewUnits: string | null;
    routeModifyCourse: string | null;
    routeCourseMarkbook: string | null;
    routeLearningAnalytics: string | null;
}
const props = defineProps<Props>();

const addStore = useAddUnitsStore();

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
            <div class="l-flex-row l-gap-s">
                <button
                    v-if="routeNewUnits"
                    class="button icon-add"
                    data-test="unit-planner-create-units"
                    @click.prevent="addStore.prompt()"
                >
                    Create {{ locale.unit.translate(true, true) }}
                </button>
                <sbx-pop-out content-id="unit-dropdown"></sbx-pop-out>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="small-12 columns">
            <ul class="meta">
                <li class="icon-quiz">Formative Assessment Task</li>
                <li class="icon-staff-students">Grades visible to staff and students</li>
            </ul>
        </div>
    </div>
    <ul id="unit-dropdown" class="f-dropdown" data-dropdown-content aria-hidden="true">
        <li class="separator">Actions</li>
        <li v-if="props.routeImportIntoClasses">
            <a
                class="icon-import"
                :data-test="`up-header-${props.courseId}-import-link`"
                :href="getReturnUrl(props.routeImportIntoClasses!)"
                >Import into {{ locale.class.translate(true, true) }}</a
            >
        </li>
        <li>
            <a href="#" class="icon-print" @click.prevent="onPrintSelected">Print Unit Planner</a>
        </li>
        <li>
            <a :href="`/homepage/${props.folderId}`" class="icon-home">View Homepage</a>
        </li>
        <li class="separator">Reports</li>
        <li>
            <a :href="`/learning/assessment/calendar?course=${props.courseId}`" class="icon-calendar"
                >Assessment Calendar</a
            >
        </li>
        <li v-if="routeCourseMarkbook">
            <a :href="`${props.routeCourseMarkbook}`" class="icon-curriculum">Course Markbook</a>
        </li>
        <li v-if="routeCurriculumCoverage">
            <a :href="`${props.routeCurriculumCoverage}?courseId=${props.courseId}`" class="icon-report"
                >Curriculum Coverage</a
            >
        </li>
        <li v-if="routeLearningAnalytics">
            <a :href="`${props.routeLearningAnalytics}`" class="icon-poll">Learning Analytics</a>
        </li>
        <li v-if="routeModifyCourse" class="separator">Manage</li>
        <li v-if="routeModifyCourse">
            <a :href="`${props.routeModifyCourse}?${getReturnUrl()}`" class="icon-modify">Modify Course</a>
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
