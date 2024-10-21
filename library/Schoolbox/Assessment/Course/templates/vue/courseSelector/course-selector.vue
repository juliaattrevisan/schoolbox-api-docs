<script setup lang="ts">
/**
 * To select Courses
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { Course } from "@schoolboxLibrary/types/Course";
import { useCourseSelectorStore } from "./store/courseSelectorStore";

interface Props {
    endpoint: string; // The course api endpoint
    preselected: Course[]; // The courses that have already been selected previously
}
const props = defineProps<Props>();

const store = useCourseSelectorStore();
store.init(props.preselected);
</script>

<template>
    <search-select
        :v-model:selected-items="store.selected"
        :v-model:results="[]"
        label="Select Courses"
        label-id="courses"
        :is-required="true"
        :endpoint="props.endpoint"
    />
</template>

<style scoped></style>
