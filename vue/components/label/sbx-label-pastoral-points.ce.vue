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

const sentiment = window.Vue.computed(() => {
    if (props.points > 0) {
        return "success";
    }
    if (props.points < 0) {
        return "alert";
    }
    return "neutral";
});
</script>

<template>
    <sbx-label :title="`${props.points} Points`" :sentiment="sentiment"></sbx-label>
</template>

<style scoped></style>
