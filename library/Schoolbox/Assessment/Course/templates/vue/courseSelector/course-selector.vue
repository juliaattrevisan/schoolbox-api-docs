<script setup lang="ts">
/**
 * To select Courses
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { useSelected } from "@schoolboxVue/composables/store/useSelected";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { SearchSelectItem } from "@schoolboxVue/components/searchSelect/types/searchSelectTypes";

interface Props {
    endpoint: string; // The course api endpoint
    preselected: Course[]; // The courses that have already been selected previously
}
const props = defineProps<Props>();

const { selected, add, remove } = useSelected();

/**
 * Add items
 * @param items - the items to add
 */
function onAdd(items: SearchSelectItem[]): void {
    add(items);
}

/**
 * Remove items
 * @param items - the items to remove
 */
function onRemove(items: SearchSelectItem[]): void {
    remove(items);
}
</script>

<template>
    <search-select
        label="Select Courses"
        label-id="courses"
        :is-required="true"
        :endpoint="props.endpoint"
        :selected-items="selected"
        @add="onAdd"
        @remove="onRemove"
    />
</template>

<style scoped></style>
