<script setup lang="ts">
/**
 * To select Courses
 */

import type { Course } from "@schoolboxLibrary/types/Course";

interface Props {
    endpoint: string; // The course api endpoint
    preselected: Course[]; // The courses that have already been selected previously
}
const props = defineProps<Props>();

/**
 * Start loading the courses data
 */
window.Vue.onMounted(() => {});
</script>

<template>
    <div></div>
</template>

<style scoped></style>
