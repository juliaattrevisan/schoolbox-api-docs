<script setup lang="ts">
/**
 * To help identify gaps in chosen curriculum areas
 */

import type { Course } from "@schoolboxLibrary/types/Course";
import CcInput from "./cc-input.vue";
import CcReport from "./cc-report.vue";
import { useStore } from "./store/curriculumCoverageStore";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
    selectedCourses: Course[]; // The pre selected courses
    reportEndpoint: string; // The report api endpoint.
}
const props = defineProps<Props>();

useStore().init(props.reportEndpoint); // just to pass the
</script>

<template>
    <h1>Curriculum Coverage Report</h1>
    <div class="l-flex-col l-gap-m">
        <cc-input
            :courses-endpoint="props.coursesEndpoint"
            :curriculum-endpoint="props.curriculumEndpoint"
            :selected-courses="props.selectedCourses"
        />
        <cc-report />
    </div>
</template>

<style scoped></style>
