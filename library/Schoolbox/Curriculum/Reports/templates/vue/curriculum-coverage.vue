<script setup lang="ts">
/**
 * To help identify gaps in chosen curriculum areas
 */

import type { Course } from "@schoolboxLibrary/types/Course";
import type { Node } from "@schoolboxCurriculum/types/Node";
import CcHeader from "./cc-header.vue";
import CcInput from "./cc-input.vue";
import CcReport from "./cc-report.vue";
import { useQueryStore } from "./store/ccQueryStore";
import { usePopOutStore } from "./store/ccPopOutStore";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
    selectedCourses: Course[]; // The pre selected courses
    selectedNodes: Node[]; // The pre selected nodes
    reportEndpoint: string; // The report api endpoint.
    usageEndpoint: string; // The usage api endpoint.
}
const props = defineProps<Props>();

useQueryStore().init(props.reportEndpoint); // pass through the report endpoint to the store
usePopOutStore().init(props.usageEndpoint); // pass through the usage endpoint to the store
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <cc-header />
        <cc-input
            :courses-endpoint="props.coursesEndpoint"
            :curriculum-endpoint="props.curriculumEndpoint"
            :selected-courses="props.selectedCourses"
            :selected-nodes="props.selectedNodes"
        />
        <cc-report />
    </div>
</template>

<style scoped></style>
