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
 * When a result is selected, add it in
 * or remove it if it already exists
 * @param result - the result selected
 */
function onSelectResult(result: Result): void {
    const exists = selected.value.find((item) => item.id === result.id);
    if (exists) {
        remove(result);
    } else {
        add(result);
    }
}

/**
 * Select all the results
 * @param results - the results to add
 */
function selectAllResults(results: Result[]): void {
    results.forEach((result) => {
        const exists = selected.value.find((item) => item.id === result.id);
        if (!exists) {
            add(result);
        }
    });
}

/**
 * Remove all the results
 * @param results - the results to remove
 */
function removeAllResults(results: Result[]): void {
    results.forEach((result) => {
        const exists = selected.value.find((item) => item.id === result.id);
        if (exists) {
            remove(result);
        }
    });
}

/**
 * Remove the result from the selected list
 * @param result - the result removed
 */
function onRemoveItem(result: Result): void {
    remove(result);
}
</script>

<template>
    <search-select
        :endpoint="props.endpoint"
        :selected-items="selected"
        placeholder="Search for courses..."
        @select-result="onSelectResult"
        @select-all-results="selectAllResults"
        @remove-all-results="removeAllResults"
        @remove-item="onRemoveItem"
    />
</template>

<style scoped></style>
