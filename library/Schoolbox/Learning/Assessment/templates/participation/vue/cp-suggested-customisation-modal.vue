<script setup lang="ts">
/**
 * To generated and display a suggested customisation as well as choose
 * what type of customisation to apply
 */

import CpAiModal from "./cp-ai-modal.vue";

const props = defineProps<{
    route: string;
    assessmentId: number;
}>();

const studentId = defineModel<number | null>("student-id", { default: null });
</script>

<template>
    <cp-ai-modal v-model:student-id="studentId" :route="props.route" :assessment-id="props.assessmentId" />
</template>

<style scoped></style>
