<script setup lang="ts">
/**
 * To choose course(s) and curriculum nodes to generate a report on
 */
import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { useStore } from "./store/curriculumCoverageStore";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
}
const props = defineProps<Props>();

const store = useStore();

/**
 * Submit the form to generate the report
 */
function onGenerateReport(): void {
    console.warn(store.selectedCourses);
    console.warn(store.nodes);
}
</script>

<template>
    <form class="c-island l-flex-col l-pad-m l-gap-m" @submit.prevent="onGenerateReport">
        <div>
            <label for="courses">Select Courses</label>
            <search-select endpoint="" :selected="store.selectedCourses" />
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
    </form>
</template>

<style scoped></style>
