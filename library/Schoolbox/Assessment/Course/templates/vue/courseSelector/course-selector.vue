<script setup lang="ts">
/**
 * To select Courses
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { useSelected } from "@schoolboxVue/composables/store/useSelected";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { Result } from "@schoolboxVue/components/searchSelect/types/Result";

interface Props {
    endpoint: string; // The course api endpoint
    preselected: Course[]; // The courses that have already been selected previously
}
const props = defineProps<Props>();

const { selected, add, remove } = useSelected();

/**
 * Select all the results
 * @param results - the results to add
 */
function onAdd(results: Result[]): void {
    add(results);
}

/**
 * Remove all the results
 * @param results - the results to remove
 */
function onRemove(results: Result[]): void {
    remove(results);
}
</script>

<template>
    <label for="courses">Select Courses</label>
    <search-select
        label-id="courses"
        :endpoint="props.endpoint"
        :selected-items="selected"
        placeholder="Search for courses..."
        @add="onAdd"
        @remove="onRemove"
    />
</template>

<style scoped></style>
