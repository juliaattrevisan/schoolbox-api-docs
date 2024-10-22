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
    label?: string; // The label text
    labelId?: string; // The label id
    isRequired?: boolean; // Whether the input requires a value
}
const props = withDefaults(defineProps<Props>(), {
    label: "Select Courses",
    labelId: "courses",
});

const store = useCourseSelectorStore();
store.init(props.preselected);
</script>

<template>
    <search-select
        v-model:selected="store.selected"
        :label="props.label"
        :label-id="props.labelId"
        :is-required="props.isRequired"
        :endpoint="props.endpoint"
    />
</template>

<style scoped></style>
