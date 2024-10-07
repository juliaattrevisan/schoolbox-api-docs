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
 * When a result is selected, add it to the selected store
 * or remove it if it already exists
 * @param result - the result selected
 */
function onSelected(result: Result): void {
    const exists = selectedStore.selected.find((item) => item.id === result.id);
    if (exists) {
        selectedStore.remove(result);
    } else {
        selectedStore.add(result);
    }
}

/**
 * Remove the result from the selected store
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
