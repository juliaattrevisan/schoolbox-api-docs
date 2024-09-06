<script setup lang="ts">
/**
 * To help identify gaps in chosen curriculum areas
 */

import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";

interface Props {
    endpoint: string; // The curriculum api endpoint.
}
const props = defineProps<Props>();
</script>

<template>
    <curriculum-selector :is-read-only="false" :data-endpoint="props.endpoint" />
</template>

<style scoped></style>
