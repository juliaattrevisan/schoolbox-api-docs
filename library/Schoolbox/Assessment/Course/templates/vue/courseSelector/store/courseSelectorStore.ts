/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";
import { useSelectedStore } from "./coseSelectedStore";

export const useStore = defineStore("courseSelector", () => {
    /**
     * store any passed through properties
     * @param preselected - the courses that were preselected
     */
    function init(preselected: Course[]): void {
        useSelectedStore().update(preselected);
    }

    return {
        init,
    };
});
