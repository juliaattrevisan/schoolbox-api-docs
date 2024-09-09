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
    <div>
        <label for="curriculum">Curriculum</label>
        <curriculum-selector
            :is-read-only="false"
            :data-endpoint="props.endpoint"
            :is-select-all="true"
            :can-select-children="false"
            input-id="curriculum"
        />
    </div>
</template>

<style scoped></style>
