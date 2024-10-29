/**
 * Manage the inputs used to generate the CC report
 */

import { defineStore } from "pinia";
import { useCourseSelectorStore } from "@schoolboxLibrary/Assessment/Course/templates/vue/courseSelector/store/courseSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";

export const COURSES_PARAM = "courseId"; // the incoming param to check for initial report
export const NODES_PARAM = "nodeId"; // the incoming param to check for initial report

export const useInputStore = defineStore(`ccInput`, () => {
    const selectedCoursesStore = useCourseSelectorStore();
    const selectedNodesStore = useSelectedNodesStore();
    const coursesValue = window.Vue.computed(() => selectedCoursesStore.selectedIds.join(","));
    const nodesValue = window.Vue.computed(() => (selectedNodesStore.nodesIds as number[]).join(","));
    const activeCoursesValue = window.Vue.ref("");
    const activeNodesValue = window.Vue.ref("");

    /**
     * report has been triggered so set the active course and nodes
     */
    function setActive(): void {
        activeCoursesValue.value = coursesValue.value;
        activeNodesValue.value = nodesValue.value;
    }

    /**
     * prevent the report from running again if the same courses and nodes are selected
     * @returns - whether there's been a change in the date range
     */
    const canReport = window.Vue.computed(() => {
        return activeCoursesValue.value !== coursesValue.value || activeNodesValue.value !== nodesValue.value;
    });

    return {
        selectedCoursesStore,
        selectedNodesStore,
        coursesValue,
        nodesValue,
        setActive,
        canReport,
    };
});
