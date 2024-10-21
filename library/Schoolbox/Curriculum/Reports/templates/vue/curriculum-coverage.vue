<script setup lang="ts">
/**
 * To help identify gaps in chosen curriculum areas
 */

import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import CcReport from "./cc-report.vue";

interface Props {
    coursesEndpoint: string; // The courses api endpoint.
    curriculumEndpoint: string; // The curriculum api endpoint.
}
const props = defineProps<Props>();

/**
 * @todo - This is a temporary solution to store the selected nodes to demo the mock report
 */
const selectedNodes = window.Vue.ref<Pick<Node, "id" | "name" | "code" | "description" | "children" | "enabled">[]>([
    {
        id: 3104,
        name: "Communication skills",
        code: "E-ATL-CO",
        description: "",
        children: [],
        enabled: true,
    },
]);
</script>

<template>
    <h1>Curriculum Coverage Report</h1>
    <div class="l-flex-col l-gap-m">
        <div class="c-island l-flex-col l-pad-m l-gap-m">
            <div>
                <label for="courses">Select Courses</label>
                <search-select endpoint="" :selected="[]" />
            </div>
            <div>
                <label for="curriculum">Select Curriculum</label>
                <curriculum-selector
                    :is-read-only="false"
                    :data-endpoint="props.curriculumEndpoint"
                    :is-select-all="true"
                    :include-without-code="true"
                    :can-select-children="false"
                    :selected-nodes="selectedNodes"
                    input-id="curriculum"
                />
            </div>
            <button>Generate report</button>
        </div>
        <div class="c-island l-pad-m">
            <cc-report />
        </div>
    </div>
</template>

<style scoped></style>
