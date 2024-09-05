<script setup lang="ts">
/**
 * The Unit planner header, containing the title and functionality to add a new unit
 * NOTE: We initially had planned to create a new unit directly from the unit planner,
 * but for now this functionality has been removed as we would just be duplicating
 * functionality provided elsewhere without also provided the ability to rename/deleting units as well.
 * The relevant code has been commented out for now as it may be added in the future however.
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";
// import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
// import { useAddUnitStore } from "./store/unitPlannerAddUnitStore";
// import UpAddUnit from "./up-add-unit.vue";

interface Props {
    courseTitle: string;
    courseId: number;
    folderId: number;
}
const props = defineProps<Props>();
// const addUnitStore = useAddUnitStore();

/**
 * To show the add unit display
 */
// function onCreateSelected() {
//     if (!addUnitStore.isOpen) {
//         addUnitStore.prompt();
//     }
// }
/**
 * To show the print option under three dot menu
 */
function onPrintSelected(): void {
    window.print();
}
</script>

<template>
    <div class="row actions-small-1 actions-medium-2">
        <div class="button-action small-12 columns">
            <h1>Unit Planner - {{ props.courseTitle }}</h1>
            <sbx-pop-out content-id="unit-dropdown"></sbx-pop-out>
            <!--
            <button
                class="button icon-add"
                data-interaction="add-unit"
                data-test-add-unit
                @click.prevent="onCreateSelected"
            >
                Create unit
            </button>
            -->
        </div>
    </div>
    <ul id="unit-dropdown" class="f-dropdown" data-dropdown-content aria-hidden="true">
        <li>
            <a :href="`/course/modify/${props.courseId}?${getReturnUrl()}`" class="icon-modify">Modify Course</a>
        </li>
        <li>
            <a :href="`/homepage/${props.folderId}`" class="icon-home">View Homepage</a>
        </li>
        <li>
            <a :href="`/learning/courseMarkbook/${props.courseId}`" class="icon-curriculum">Course Markbook</a>
        </li>
        <li>
            <a :href="`/learning/assessment/calendar?course=${props.courseId}`" class="icon-calendar"
                >Assessment Calendar</a
            >
        </li>
        <li>
            <a href="#" class="icon-print" @click.prevent="onPrintSelected">Print Unit Planner</a>
        </li>
    </ul>
    <!--
    <Transition!-- @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <up-add-unit v-if="addUnitStore.isOpen" />
    </Transition!-->
</template>

<style scoped>
/* Styles for printing */
@media print {
    ul#unit-dropdown {
        display: none !important;
    }
}
</style>
