<script setup lang="ts">
/**
 * To choose course(s) and curriculum nodes to generate a report on
 */
import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { Course } from "@schoolboxLibrary/types/Course";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
    selectedCourses: Course[]; // The pre selected courses
}
const props = defineProps<Props>();
</script>

<template>
    <div class="c-island l-flex-col l-pad-m l-gap-m">
        <div>
            <label for="courses">Select Courses</label>
            <search-select endpoint="" :selected="props.selectedCourses" />
        </div>
        <div>
            <label for="curriculum">Select Curriculum</label>
            <curriculum-selector
                :is-read-only="false"
                :data-endpoint="props.curriculumEndpoint"
                :is-select-all="true"
                :include-without-code="true"
                :can-select-children="false"
                input-id="curriculum"
            />
        </div>
        <button>Generate report</button>
    </div>
</template>

<style scoped></style>
