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
function onSelected(result: Result): void {
    const exists = selected.value.find((item) => item.id === result.id);
    if (exists) {
        remove(result);
    } else {
        add(result);
    }
}

/**
 * Remove the result from the selected list
 * @param result - the result removed
 */
function onRemoved(result: Result): void {
    remove(result);
}
</script>

<template>
    <search-select
        :endpoint="props.endpoint"
        :selected="selected"
        placeholder="Search for courses..."
        @select="onSelected"
        @remove="onRemoved"
    />
</template>

<style scoped></style>
