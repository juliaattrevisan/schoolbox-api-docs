<script setup lang="ts">
/**
 * To select Courses
 */

import { watchIgnorable } from "@vueuse/core";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { Course } from "@schoolboxLibrary/types/Course";
import { useCourseSelectorStore } from "./store/courseSelectorStore";

interface Props {
    endpoint: string; // The course api endpoint
    preselected: Course[]; // The courses that have already been selected previously
    label?: string; // The label text
    labelId?: string; // The label id
    isRequired?: boolean; // Whether the input requires a value
    hasError?: boolean; // Whether the input has an error
    errorMessage?: string; // The error message to display
    isReadOnly?: boolean; // Whether the input is read only
}
const props = withDefaults(defineProps<Props>(), {
    label: "Select Courses",
    labelId: "courses",
    errorMessage: "Please select at least one course",
});

const store = useCourseSelectorStore();
store.init(props.preselected);

const results = window.Vue.ref<Course[]>([]);

/**
 * To manipulate the results to target the courses object and
 * use the title as the name
 */
const { ignoreUpdates } = watchIgnorable(results, (newResults) => {
    ignoreUpdates(() => {
        if (newResults.length === 0) {
            return;
        }
        results.value = newResults.courses.map((course) => {
            return {
                id: course.id,
                name: course.title,
            };
        });
    });
});
</script>

<template>
    <search-select
        v-model:selected="store.selected"
        v-model:results="results"
        :label="props.label"
        :label-id="props.labelId"
        :is-required="props.isRequired"
        :endpoint="props.endpoint"
        :has-error="props.hasError"
        :error-message="props.errorMessage"
        :is-read-only="props.isReadOnly"
    />
</template>

<style scoped></style>
