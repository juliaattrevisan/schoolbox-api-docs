/**
 *
 */

import { defineStore } from "pinia";
import type { Student } from "../types/Student";

export const useStudentsStore = defineStore("hmStudents", () => {
    const students = window.Vue.ref<Student[]>([]);

    /**
     * store any passed through properties
     */
    function init($students: object) {
        for (const [key, value] of Object.entries($students)) {
            students.value.push({ elementId: key as string, ...value });
        }
    }

    return {
        init,
        students,
    };
});
