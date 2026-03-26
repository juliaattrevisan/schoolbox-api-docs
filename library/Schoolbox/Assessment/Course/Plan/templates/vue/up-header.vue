<script setup lang="ts">
/**
 * The Unit planner header, containing the title
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";
import locale from "@js/terminology/rosettaStone";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { Node } from "@schoolboxCurriculum/types/Node";
import CurriculumCodes from "@schoolboxCurriculum/vue/curriculumCodes/curriculum-codes.vue";
import { useAddUnitsStore } from "./store/unitPlannerAddUnitsStore";
import type { Routes } from "./unitPlannerTypes";

interface Props {
    course: Course;
    curriculumNodes: Node[];
    folderId: number;
    routes: Routes;
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
            <h1>Unit Planner - {{ props.course.title }}</h1>
            <div class="l-flex-row l-gap-s">
                <button
                    v-if="routes.newUnits"
                    class="button sbx-button--has-icon"
                    data-test="unit-planner-create-units"
                    @click.prevent="addStore.prompt()"
                >
                    <sbx-icon name="add"></sbx-icon>
                    Create {{ locale.unit.translate(true, true) }}
                </button>
                <sbx-pop-out content-id="unit-dropdown"></sbx-pop-out>
            </div>
        </div>
    </div>
    <div v-if="props.course.subjectCode || props.curriculumNodes.length > 0" class="row">
        <div class="small-12 columns">
            <ul class="l-mar--0-left">
                <li v-if="props.course.subjectCode" class="meta l-flex-row l-flex--align-start l-gap-s">
                    <sbx-icon name="subject-code"></sbx-icon>Subject Code: {{ props.course.subjectCode }}
                </li>
                <li
                    v-if="props.curriculumNodes.length > 0"
                    class="meta l-flex-row l-flex--align-start l-flex--no-wrap l-gap-s"
                >
                    <sbx-icon name="curriculum"></sbx-icon>
                    <div>
                        <curriculum-codes :nodes="props.curriculumNodes" />
                    </div>
                </li>
            </ul>
        </div>
    </div>
    <ul id="unit-dropdown" class="f-dropdown" data-dropdown-content aria-hidden="true">
        <li class="separator">Actions</li>
        <li v-if="props.routes.importIntoClasses">
            <a
                class="icon-import"
                :data-test="`up-header-${props.course.id}-import-link`"
                :href="getReturnUrl(props.routes.importIntoClasses!)"
                >Import into {{ locale.class.translate(true, true) }}</a
            >
        </li>
        <li>
            <a href="#" class="sbx-button--has-icon" @click.prevent="onPrintSelected"
                ><sbx-icon name="print" />Print Unit Planner</a
            >
        </li>
        <li>
            <a :href="`/homepage/${props.folderId}`" class="sbx-button--has-icon"
                ><sbx-icon name="home" />View Homepage</a
            >
        </li>
        <li class="separator">Reports</li>
        <li>
            <a :href="`/learning/assessment/calendar?course=${props.course.id}`" class="sbx-button--has-icon"
                ><sbx-icon name="calendar" />Assessment Calendar</a
            >
        </li>
        <li v-if="routes.courseMarkbook">
            <a :href="`${props.routes.courseMarkbook}`" class="sbx-button--has-icon"
                ><sbx-icon name="curriculum" />Course Markbook</a
            >
        </li>
        <li v-if="routes.curriculumCoverage">
            <a :href="`${props.routes.curriculumCoverage}?courseId=${props.course.id}`" class="sbx-button--has-icon"
                ><sbx-icon name="report" />Curriculum Coverage</a
            >
        </li>
        <li v-if="routes.learningAnalytics">
            <a :href="`${props.routes.learningAnalytics}`" class="sbx-button--has-icon"
                ><sbx-icon name="poll" />Learning Analytics</a
            >
        </li>
        <li v-if="routes.modifyCourse" class="separator">Manage</li>
        <li v-if="routes.modifyCourse">
            <a :href="`${props.routes.modifyCourse}?${getReturnUrl()}`" class="sbx-button--has-icon"
                ><sbx-icon name="modify" />Modify Course</a
            >
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
