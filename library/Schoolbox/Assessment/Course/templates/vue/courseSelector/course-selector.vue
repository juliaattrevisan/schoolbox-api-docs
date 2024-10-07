<script setup lang="ts">
/**
 * To select Courses
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { Result } from "@schoolboxVue/components/searchSelect/types/Result";
import { useSelectedStore } from "@schoolboxVue/store/useSelectedStore";

interface Props {
    endpoint: string; // The course api endpoint
    preselected: Course[]; // The courses that have already been selected previously
}
const props = defineProps<Props>();

const selectedStore = useSelectedStore();

/**
 * We only allow one creator to be selected at a time,
 * so only select a result if there isn't one already selected
 * or unselect the current result if it's already selected
 * @param result - the result selected
 */
function onSelected(result: Result): void {
    selectedStore.add(result);
}

/**
 * Remove the result, as we only allow one creator to be selected at a time
 * we can simply just clear the store
 * @param result - the result removed
 */
function onRemoved(result: Result): void {
    selectedStore.remove(result);
}

/**
 * Start loading the courses data
 */
window.Vue.onMounted(() => {});
</script>

<template>
    <search-select
        :endpoint="props.endpoint"
        :selected="selectedStore.selected"
        placeholder="Search for courses..."
        @select="onSelected"
        @remove="onRemoved"
    />
</template>

<style scoped></style>
