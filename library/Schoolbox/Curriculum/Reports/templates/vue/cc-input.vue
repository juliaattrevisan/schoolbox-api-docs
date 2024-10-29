<script setup lang="ts">
/**
 * To choose course(s) and curriculum nodes to generate a report on
 */
import CourseSelector from "@schoolboxLibrary/Assessment/Course/templates/vue/courseSelector/course-selector.vue";
import type { Course } from "@schoolboxLibrary/types/Course";
import CcInputCurriculum from "./cc-input-curriculum.vue";
import { useInputStore } from "./store/ccInputStore";
import { useReportStore } from "./store/ccReportStore";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
    selectedCourses: Course[]; // The pre selected courses
}
const props = defineProps<Props>();

const inputStore = useInputStore();
const reportStore = useReportStore();
const isCoursesInvalid = window.Vue.ref(false);
const isCurriculumInvalid = window.Vue.ref(false);

/**
 * The text to display on the button
 * @returns - The text to display on the button
 */
const buttonText = window.Vue.computed(() => {
    return reportStore.isLoading ? "Generating report..." : "Generate report";
});

/**
 * Submit the form to generate the report
 */
function onGenerateReport(): void {
    if (reportStore.isLoading) {
        // Prevent multiple submissions
        return;
    }
    isCoursesInvalid.value = false;
    isCurriculumInvalid.value = false;
    if (inputStore.selectedCoursesStore.selected.length === 0) {
        isCoursesInvalid.value = true;
    }
    if (inputStore.selectedNodesStore.nodes.length === 0) {
        isCurriculumInvalid.value = true;
        return;
    }
    if (!reportStore.canReport) {
        // avoid generating the same report multiple times
        return;
    }
    void reportStore.report();
}
</script>

<template>
    <form class="c-island l-flex-col l-pad-m l-gap-m" @submit.prevent="onGenerateReport">
        <div>
            <course-selector
                :endpoint="props.coursesEndpoint"
                :preselected="props.selectedCourses"
                :is-required="true"
                :has-error="isCoursesInvalid"
            />
        </div>
        <div>
            <cc-input-curriculum
                :endpoint="props.curriculumEndpoint"
                :is-required="true"
                :has-error="isCurriculumInvalid"
            />
        </div>
        <button>{{ buttonText }}</button>
    </form>
</template>

<style scoped></style>
