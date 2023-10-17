<script setup lang="ts">
/**
 * Display a single student's responses
 */

import type { Student } from "./types/Student";

interface Props {
    student: Student; //
}
const props = defineProps<Props>();
</script>

<template>
    <h2>{{ props.student.name }}</h2>
    <div :id="props.student.elementId"></div>
</template>

<style scoped></style>
