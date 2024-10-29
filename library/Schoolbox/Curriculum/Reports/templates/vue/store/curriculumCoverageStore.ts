/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";

export const useStore = defineStore("curriculumCoverage", () => {
    const selectedCourses = window.Vue.ref<Course[]>([]); // the selected courses @todo - this will be stored in a selectedCoursesStore instead with SA-18582

    /**
     * store any passed through properties
     * @param $courses - the courses to display in the report
     */
    function init($courses: Course[]): void {
        selectedCourses.value = $courses;
    }

    return {
        selectedCourses,
        init,
    };
});
