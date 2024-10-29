<script setup lang="ts">
/**
 * To choose course(s) and curriculum nodes to generate a report on
 */
import CourseSelector from "@schoolboxLibrary/Assessment/Course/templates/vue/courseSelector/course-selector.vue";
import { useCourseSelectorStore } from "@schoolboxLibrary/Assessment/Course/templates/vue/courseSelector/store/courseSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import type { Course } from "@schoolboxLibrary/types/Course";
import { useStore } from "./store/curriculumCoverageStore";
import CcInputCurriculum from "./cc-input-curriculum.vue";
import { useReportStore } from "./store/ccReportStore";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
    selectedCourses: Course[]; // The pre selected courses
}
const props = defineProps<Props>();

const store = useStore();
const reportStore = useReportStore();
const selectedCoursesStore = useCourseSelectorStore();
const selectedNodesStore = useSelectedNodesStore();
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
        return;
    }
    isCoursesInvalid.value = false;
    isCurriculumInvalid.value = false;
    if (selectedCoursesStore.selected.length === 0) {
        isCoursesInvalid.value = true;
    }
    if (selectedNodesStore.nodes.length === 0) {
        isCurriculumInvalid.value = true;
        return;
    }
    store.isLoading = true;
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
