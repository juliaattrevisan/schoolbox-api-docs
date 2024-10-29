/**
 * Manage the inputs used to generate the CC report
 */

import { defineStore } from "pinia";
import { useCourseSelectorStore } from "@schoolboxLibrary/Assessment/Course/templates/vue/courseSelector/store/courseSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";

export const useInputStore = defineStore(`ccInput`, () => {
    const selectedCoursesStore = useCourseSelectorStore();
    const selectedNodesStore = useSelectedNodesStore();

    return {
        selectedCoursesStore,
        selectedNodesStore,
    };
});
