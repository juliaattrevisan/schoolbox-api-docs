<script setup lang="ts">
/**
 * To search and select Courses
 */

import { watchIgnorable } from "@vueuse/core";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import SearchSelectModal from "@schoolboxVue/components/searchSelect/modal/search-select-modal.vue";
import type { Course } from "@schoolboxLibrary/types/Course";
import { useCourseSelectorStore } from "./store/courseSelectorStore";

type CourseAPIResponse = {
    courses: Course[];
};

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
 * To manipulate the results to target the courses object
 */
const { ignoreUpdates } = watchIgnorable(results, (newResults: CourseAPIResponse | unknown[]) => {
    // ignore updates to avoid an infinite loop...
    ignoreUpdates(() => {
        if (newResults instanceof Array && newResults.length === 0) {
            return;
        }
        if (typeof newResults === "object" && "courses" in newResults) {
            results.value = newResults.courses;
        }
    });
});

/**-----------------------------------------------
 * Modal related features
 ------------------------------------------------*/

const isModalOpen = window.Vue.ref(false);
const modalResults = window.Vue.ref<Course[]>([]);

/**
 * To select a link to the course
 * @param course - the selected course
 */
function onCourseNameSelect(course: Course): void {
    if (course.folder) {
        window.location.href = course.folder._links.view;
    }
}
/*
 * To open up the course selector modal
 */
function onButtonSelect(): void {
    isModalOpen.value = true;
}

/**
 * To manipulate the results to target the courses object
 */
const { ignoreUpdates: ignoreModalUpdates } = watchIgnorable(
    modalResults,
    (newResults: CourseAPIResponse | unknown[]) => {
        // ignore updates to avoid an infinite loop...
        ignoreModalUpdates(() => {
            if (newResults instanceof Array && newResults.length === 0) {
                return;
            }
            if (typeof newResults === "object" && "courses" in newResults) {
                modalResults.value = newResults.courses;
            }
        });
    },
);
</script>

<template>
    <search-select
        v-model:selected="store.selected"
        v-model:results="results"
        :label="props.label"
        :label-id="props.labelId"
        placeholder="Search for courses..."
        :is-required="props.isRequired"
        :endpoint="`${props.endpoint}`"
        :has-error="props.hasError"
        :error-message="props.errorMessage"
        :is-read-only="props.isReadOnly"
        :allow-partial-matches="true"
        :can-select-item="true"
        @select="onCourseNameSelect"
        placeholder="Search for courses..."
        :has-button="true"
        button-icon="icon-course"
        @button-select="onButtonSelect"
    />
    <search-select-modal
        v-model:selected="store.selected"
        v-model:is-open="isModalOpen"
        v-model:results="modalResults"
        label="Search"
        :label-id="`${props.labelId}-modal-search`"
        placeholder="Search for courses..."
        title="Browse Courses"
        :endpoint="`${props.endpoint}`"
        :allow-partial-matches="true"
    />
</template>

<style scoped></style>
