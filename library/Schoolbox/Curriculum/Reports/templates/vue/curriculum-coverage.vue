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
}
const props = defineProps<Props>();

const store = useStore();

/**
 * Initialise the store with the selected courses
 */
window.Vue.onMounted(() => {
    store.init(props.selectedCourses);
});
</script>

<template>
    <h1>Curriculum Coverage Report</h1>
    <div class="l-flex-col l-gap-m">
        <cc-input :courses-endpoint="props.coursesEndpoint" :curriculum-endpoint="props.curriculumEndpoint" />
        <cc-report />
    </div>
</template>

<style scoped></style>
