<script setup lang="ts">
/**
 * To help identify gaps in chosen curriculum areas
 */

import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
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
                <div>
                    <div data-v-75ed7460="" class="selected-nodes-container" data-test-nodes-container="">
                        <div data-v-3596dc9a="" data-v-75ed7460="" class="selected-node" data-test-node="">
                            <button
                                data-v-3596dc9a=""
                                type="button"
                                class="title-button"
                                title="View Communication skills"
                                data-test-title-button=""
                            >
                                Year 9 English
                            </button>
                            <button
                                data-v-3596dc9a=""
                                type="button"
                                class="remove-button icon-close"
                                title="Remove Communication skills"
                                data-test-remove-button=""
                            ></button>
                        </div>
                        <div data-v-3596dc9a="" data-v-75ed7460="" class="selected-node" data-test-node="">
                            <button
                                data-v-3596dc9a=""
                                type="button"
                                class="title-button"
                                title="View Communication skills"
                                data-test-title-button=""
                            >
                                Year 9 Humanities
                            </button>
                            <button
                                data-v-3596dc9a=""
                                type="button"
                                class="remove-button icon-close"
                                title="Remove Communication skills"
                                data-test-remove-button=""
                            ></button>
                        </div>
                    </div>
                    <div data-v-3fcd9114="" class="input-group" data-is-small-container="false">
                        <input
                            id="courses"
                            data-v-3fcd9114=""
                            type="text"
                            class="search-input"
                            placeholder="Search for courses or keywords..."
                            data-search-select-search-input=""
                        />
                        <button
                            data-v-3fcd9114=""
                            type="button"
                            class="button icon-course"
                            title="Browse and select from the Courses"
                        >
                            Browse
                        </button>
                    </div>
                </div>
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
