<script setup lang="ts">
/**
 * To display a label
 */

interface Props {
    /**
     * The title of the label
     */
    title: string;
    /**
     * The sentiment of the label, which impacts the color of the label
     */
    sentiment: "success" | "warning" | "alert" | "neutral";
}

const props = withDefaults(defineProps<Props>(), {
    title: "",
    sentiment: "neutral",
});
</script>

<template>
    <span :class="props.sentiment">{{ props.title }}</span>
</template>

<style scoped></style>
