<script setup lang="ts">
/**
 * To help identify gaps in chosen curriculum areas
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import type { Course } from "@schoolboxLibrary/types/Course";
import CcHeader from "./cc-header.vue";
import CcInput from "./cc-input.vue";
import CcReport from "./cc-report.vue";
import { useQueryStore } from "./store/ccQueryStore";
import { usePopOutStore } from "./store/ccPopOutStore";
import { useExportStore } from "./store/ccExportStore";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
    selectedCourses: Course[]; // The pre selected courses
    selectedNodes: Node[]; // The pre selected nodes
    reportEndpoint: string; // The report api endpoint.
    usageEndpoint: string; // The usage api endpoint.
    exportEndpoint: string; // The export endpoint.
}
const props = defineProps<Props>();

/**
 * Remaps the Courses to have a name property instead of title,
 * so they can be used in the SearchSelect component
 */
const selectedCoursesWithNames = props.selectedCourses.map((course) => ({
    ...course,
    name: course.title,
}));

useQueryStore().init(props.reportEndpoint); // pass through the report endpoint to the store
usePopOutStore().init(props.usageEndpoint); // pass through the usage endpoint to the store
useExportStore().init(props.exportEndpoint); // pass through the export endpoint to the store
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <cc-header />
        <cc-input
            :courses-endpoint="props.coursesEndpoint"
            :curriculum-endpoint="props.curriculumEndpoint"
            :selected-courses="selectedCoursesWithNames"
            :selected-nodes="props.selectedNodes"
        />
        <cc-report />
    </div>
</template>

<style scoped></style>
