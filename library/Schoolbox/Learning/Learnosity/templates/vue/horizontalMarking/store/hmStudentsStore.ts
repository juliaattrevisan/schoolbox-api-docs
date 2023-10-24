/**
 * Store the students, and link them to their reports and items
 */

import { defineStore } from "pinia";
import { useStore } from "./horizontalMarkingStore";
import { useItemsStore } from "./hmItemsStore";
import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import type { StudentItemResponse } from "../types/StudentItemResponse";

export const useStudentsStore = defineStore("hmStudents", () => {
    const students = window.Vue.ref<Student[]>([]);
    const isExpanded = window.Vue.ref<boolean>(false);

    /**
     * store any passed through properties
     * @param {object} $students - the students
     */
    function init($students: Student[]) {
        $students.forEach((student) => {
            students.value.push({
                ...student,
                isReportLoaded: false,
                isExpanded: false,
                hasReport: false,
                items: [],
            });
        });
    }

    /**
     * Get the response for a student item
     * @param {string} learnosityReference - the learnosity reference
     * @returns {StudentItemResponse} - the response
     */
    function getResponse(learnosityReference: string) {
        const response = useStore().itemResponses.find(
            (itemResponse) => itemResponse.learnosityReference === learnosityReference
        ) as StudentItemResponse;
        return response;
    }

    /**
     * Add items to a student
     * Note that the items are stored per student to link them to their report
     * The itemsStore is used to reference all items within the quiz, and these are NOT linked to the student reports
     * @param {string} reportId - the report id
     * @param {StudentItem[]} items - the items to add
     */
    function addItems(reportId: string, items: StudentItem[]) {
        const itemsWithQuestionsOnly = items.filter((item) => item.questions.length > 0);
        const studentForItems = students.value.find((student) => student.elementId === reportId);
        if (studentForItems) {
            const studentItems: StudentItem[] = [];
            itemsWithQuestionsOnly.forEach((item) => {
                // we only want to store the reference, questions and response_ids
                studentItems.push({
                    reference: item.reference,
                    questions: item.questions,
                    response_ids: item.response_ids,
                    response: getResponse(item.response_ids[0]),
                });
            });
            studentForItems.items = studentItems;
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
        // check if all student reports are loaded and if so, set the central store to loaded
        if (students.value.every((student) => student.isReportLoaded)) {
            useStore().isLoaded = true;
        }
    }

    /**
     * To move to the next student report, if that's the last student, move to the next item
     * @param {Student} student - the student to move from
     */
    function progressReport(student: Student) {
        const studentIndex = students.value.findIndex(($student) => $student.elementId === student.elementId);
        if (studentIndex < students.value.length - 1) {
            student.isExpanded = false;
            const nextStudent = students.value[studentIndex + 1];
            nextStudent.isExpanded = true;
            return;
            // shift focus them somehow?
        }
        if (studentIndex === students.value.length - 1 && useItemsStore().hasNextItem) {
            student.isExpanded = false;
            useItemsStore().toNextItem();
            const nextStudent = students.value[0];
            nextStudent.isExpanded = true;
        }
    }

    /**
     * To update a student
     * @param {Student} student - the student to update
     */
    function updateStudentReport(student: Student) {
        // send the student payload
        // once done move to the next student
        progressReport(student);
    }

    return {
        init,
        addItems,
        students,
        isExpanded,
        toggleStudent,
        toggleAllStudents,
        reportLoaded,
        updateStudentReport,
    };
});
