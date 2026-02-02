<script setup lang="ts">
/**
 * Load in pastoral flags for a group of students and display in specified container elements
 */

const props = defineProps<{
    url: string;
}>();
</script>

<template>
    <div>{{ props.url }}</div>
</template>

<style scoped></style>
