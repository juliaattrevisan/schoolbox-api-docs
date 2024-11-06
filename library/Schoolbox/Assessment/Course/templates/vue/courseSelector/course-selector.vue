<script setup lang="ts">
/**
 * To search and select Courses
 */

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
 * @param json - the returned json
 */
function onSearchResponse(json: unknown): void {
    if (typeof json === "object" && json !== null && "data" in json) {
        const newResults = json.data as CourseAPIResponse;
        results.value = newResults.courses;
    }
}

/**-----------------------------------------------
 * Modal related features
 ------------------------------------------------*/

const isModalOpen = window.Vue.ref(false);
const modalDefaultResults = window.Vue.ref<Course[]>([]); // to display results when the search input is empty
const modalSearchResults = window.Vue.ref<Course[]>([]); // to store the search results

/**
 * To select a link to the course
 * @param course - the selected course
 */
function onCourseNameSelect(course: Course): void {
    if (course.folder) {
        window.location.href = course.folder._links.view;
    }
}

/**
 *  To open up the course selector modal
 */
function onButtonSelect(): void {
    isModalOpen.value = true;
}

/**
 * To manipulate the results to target the courses object, and display the results
 * @param json - the returned json
 * @param isActive - whether the search is active
 */
function onModalSearchResponse(json: unknown, isActive: boolean): void {
    if (typeof json !== "object" || json === null || "data" in json === false) {
        return;
    }
    const newResults = json.data as CourseAPIResponse;
    if (isActive) {
        modalSearchResults.value = newResults.courses;
        return;
    }
    modalDefaultResults.value = newResults.courses;
}

/**
 * To manipulate the results to target the courses object, and display the results
 * @param json - the returned json
 * @param isActive - whether the search is active
 */
function onModalNextSearchResponse(json: unknown, isActive: boolean): void {
    if (typeof json !== "object" || json === null || "data" in json === false) {
        return;
    }
    const newResults = json.data as CourseAPIResponse;
    if (isActive) {
        modalSearchResults.value = [...modalSearchResults.value, ...newResults.courses];
        return;
    }
    modalDefaultResults.value = [...modalDefaultResults.value, ...newResults.courses];
}
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
        :manage-search-response="true"
        :has-error="props.hasError"
        :error-message="props.errorMessage"
        :is-read-only="props.isReadOnly"
        :allow-partial-matches="true"
        :can-select-item="true"
        :has-button="true"
        button-icon="icon-course"
        @search-response="onSearchResponse"
        @select="onCourseNameSelect"
        @button-select="onButtonSelect"
    />
    <search-select-modal
        v-model:selected="store.selected"
        v-model:is-open="isModalOpen"
        v-model:results="modalSearchResults"
        :default-results="modalDefaultResults"
        label="Search"
        :label-id="`${props.labelId}-modal-search`"
        placeholder="Search for courses..."
        title="Browse Courses"
        :endpoint="`${props.endpoint}`"
        :manage-search-response="true"
        :allow-partial-matches="true"
        @search-response="onModalSearchResponse"
        @next-search-response="onModalNextSearchResponse"
    />
</template>

<style scoped></style>
