<script setup lang="ts">
/**
 * The Unit planner header, containing the title and functionality to add a new unit
 */

interface Props {
    courseTitle: string;
}
const props = defineProps<Props>();
</script>

<template>
    <h1>Unit Planner - {{ props.courseTitle }}</h1>
</template>

<style scoped></style>
