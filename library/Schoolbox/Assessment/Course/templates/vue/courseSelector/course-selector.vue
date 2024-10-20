<script setup lang="ts">
/**
 * To select Courses
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { Course } from "@schoolboxLibrary/types/Course";

interface Props {
    endpoint: string; // The course api endpoint
    preselected: Course[]; // The courses that have already been selected previously
}
const props = defineProps<Props>();

const selectedItems = window.Vue.ref<Course[]>([
    { id: 1, name: "Course 1" },
    { id: 2, name: "Course 2" },
]);

window.Vue.watch(selectedItems, (newValue) => {
    console.warn("Selected Items: ", newValue.length);
});
</script>

<template>
    <search-select
        v-model:selected-items="selectedItems"
        label="Select Courses"
        label-id="courses"
        :is-required="true"
        :endpoint="props.endpoint"
    />
</template>

<style scoped></style>
