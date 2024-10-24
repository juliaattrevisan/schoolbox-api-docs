/**
 * To maintain a selection of courses
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";

export const useCourseSelectorStore = defineStore("courseSelectorStore", () => {
    const selected = window.Vue.ref<Course[]>([]); // the selected courses
    const selectedIds = window.Vue.computed(() => selected.value.map((course) => course.id)); // the ids of the selected courses

    /**
     * Initialize the store, passing through the selected courses if set
     * @param $selected - the selected courses
     */
    function init($selected: Course[] = []): void {
        selected.value = $selected;
    }

    return {
        selected,
        selectedIds,
        init,
    };
});
