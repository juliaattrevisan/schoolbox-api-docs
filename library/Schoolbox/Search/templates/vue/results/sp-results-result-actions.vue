<script setup lang="ts">
/**
 * To provide access to further actions on a result
 */

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();
</script>

<template>
    <div>Action</div>
</template>

<style scoped></style>
