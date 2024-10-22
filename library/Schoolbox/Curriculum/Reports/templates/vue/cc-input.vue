<script setup lang="ts">
/**
 * To choose course(s) and curriculum nodes to generate a report on
 */
import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import { useStore } from "./store/curriculumCoverageStore";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
}
const props = defineProps<Props>();

const store = useStore();
const selectedNodesStore = useSelectedNodesStore();
const isCoursesInvalid = window.Vue.ref(false);
const isCurriculumInvalid = window.Vue.ref(false);

/**
 * The text to display on the button
 * @returns - The text to display on the button
 */
const buttonText = window.Vue.computed(() => {
    return store.isLoading ? "Generating report..." : "Generate report";
});

/**
 * Submit the form to generate the report
 */
function onGenerateReport(): void {
    if (store.isLoading) {
        return;
    }
    isCoursesInvalid.value = false;
    isCurriculumInvalid.value = false;
    if (store.selectedCourses.length === 0) {
        isCoursesInvalid.value = true;
    }
    if (selectedNodesStore.nodes.length === 0) {
        isCurriculumInvalid.value = true;
        return;
    }
    store.isLoading = true;
}
</script>

<template>
    <form class="c-island l-flex-col l-pad-m l-gap-m" @submit.prevent="onGenerateReport">
        <div>
            <label for="courses">Select Courses</label>
            <search-select endpoint="" :selected="store.selectedCourses" />
            <label v-if="isCoursesInvalid" class="error" for="courses">This field is required.</label>
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
            <label v-if="isCurriculumInvalid" class="error" for="curriculum">This field is required.</label>
        </div>
        <button>{{ buttonText }}</button>
    </form>
</template>

<style scoped></style>
