/**
 *
 */

import { defineStore } from "pinia";
import type { Student } from "../types/Student";

export const useStudentsStore = defineStore("hmStudents", () => {
    const students = window.Vue.ref<Student[]>([]);

    /**
     * store any passed through properties
     * @param {object} $students - the students
     */
    function init($students: object) {
        for (const [key, value] of Object.entries($students)) {
            students.value.push({
                ...value,
                elementId: key as string,
                isReportLoaded: false,
                isExpanded: false,
                hasReport: false,
            });
        }
    }

    /**
     * To notify that a report linked to a student has been loaded
     * @param {number} reportId - the report id
     */
    function reportLoaded(reportId: string) {
        const studentLoaded = students.value.find((student) => student.elementId === reportId);
        if (studentLoaded) {
            studentLoaded.isReportLoaded = true;
        }
    }

    return {
        init,
        students,
        reportLoaded,
    };
});
