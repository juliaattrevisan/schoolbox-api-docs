<script setup lang="ts">
/**
 * To display pastoral points
 */

interface Props {
    /**
     * The number of points
     */
    points: number;
}

const props = withDefaults(defineProps<Props>(), {
    points: 0,
});
</script>

<template>
    <sbx-label :title="`${props.points} Points`"></sbx-label>
</template>

<style scoped></style>
