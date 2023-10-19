/**
 *
 */

import { defineStore } from "pinia";
import type { Student } from "../types/Student";

export const useStudentsStore = defineStore("hmStudents", () => {
    const students = window.Vue.ref<Student[]>([]);
    const isExpanded = window.Vue.ref<boolean>(false);

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
     * To show/hide a student
     * @param {Student} student - the student to change
     */
    function toggleStudent(student: Student) {
        student.isExpanded = !student.isExpanded;
        // if all students are now expanded, set the overall toggle to true
        if (students.value.every(($student) => $student.isExpanded)) {
            isExpanded.value = true;
        }
    }

    /**
     * To show/hide all students
     */
    function toggleAllStudents() {
        isExpanded.value = !isExpanded.value;
        students.value.forEach((student) => {
            student.isExpanded = isExpanded.value;
        });
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
        isExpanded,
        toggleStudent,
        toggleAllStudents,
        reportLoaded,
    };
});
