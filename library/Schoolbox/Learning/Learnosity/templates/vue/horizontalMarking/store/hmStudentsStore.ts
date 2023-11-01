/**
 * Store the students, and link them to their reports and items
 */

import { defineStore } from "pinia";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import {
    getItemQuestions,
    getAttemptResponse,
    getItemIsMarked,
    getStudentIsMarked,
    getUrls,
} from "@schoolboxLearnosity/js/util/markingUtils";
import { useStore } from "./horizontalMarkingStore";
import { useItemsStore } from "./hmItemsStore";

export const useStudentsStore = defineStore("hmStudents", () => {
    const students = window.Vue.ref<Student[]>([]);
    const isExpanded = window.Vue.ref<boolean>(true);
    const store = useStore();

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
     * Add items to a student
     * Note that the items are stored per student to link them to their report
     * The itemsStore is used to reference all items within the quiz, and these are NOT linked to the student reports
     * @param {string} reportId - the report id
     * @param {StudentItem[]} $items - the items to add
     */
    function addItems(reportId: string, $items: StudentItem[]) {
        const items = $items.filter((item) => item.questions.length > 0); // remove any items that don't have questions
        const selectedStudent = students.value.find((student) => student.elementId === reportId);
        if (selectedStudent) {
            const studentItems: StudentItem[] = [];
            items.forEach((item) => {
                // for each item, we only want to store the below properties, removing any others Learnosity may have provided
                studentItems.push({
                    reference: item.reference,
                    questions: getItemQuestions(store.itemResponses, item.questions),
                });
            });
            selectedStudent.items = studentItems;
            // need to do after as we rely on questions and responses being set first
            selectedStudent.items.forEach((item) => {
                item.isMarked = getItemIsMarked(item);
            });
            selectedStudent.isMarked = getStudentIsMarked(selectedStudent);
            selectedStudent.attemptComment = getAttemptResponse(store.attemptComments, selectedStudent.id);
            selectedStudent.urls = getUrls(store.urls, selectedStudent.id);
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
        // if all students are now collapsed, set the overall toggle to false
        else if (students.value.every(($student) => !$student.isExpanded)) {
            isExpanded.value = false;
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
        // check if all student reports are loaded and if so, set the central store can be considered loaded
        store.isLoaded = students.value.every((student) => student.isReportLoaded);
    }

    /**
     * To update the score of a question
     * @param {Student} student - the student to update
     * @param {StudentItem} item - the item that contains the question to update
     * @param {StudentItemQuestion} question - the question to update
     * @param {number} score - the score to update
     */
    function updateItemResponseScore(
        student: Student,
        item: StudentItem,
        question: StudentItemQuestion,
        score: number
    ) {
        question.response.score = score;
        item.isMarked = getItemIsMarked(item);
        student.isMarked = getStudentIsMarked(student);
    }

    /**
     * To update the comment of an item
     * @param {StudentItemQuestion} question - the question to update
     * @param {string} comment - the comment to update
     */
    function updateItemComment(question: StudentItemQuestion, comment: string) {
        question.response.comment = comment;
    }

    /**
     * To update the overall comment response to an attempt
     * @param {Student} student - the student to update
     * @param {string} comment - the comment to update
     */
    function updateAttemptComment(student: Student, comment: string) {
        student.attemptComment.comment = comment;
    }

    /**
     * To clear the focus from all students
     */
    function clearHasFocus() {
        students.value.forEach((student) => {
            student.hasFocus = false;
        });
    }

    /**
     * Clear focus every time a change is made, progressReport will set the focus to the next student
     */
    window.Vue.watch(
        () => useItemsStore().activeItem,
        () => {
            clearHasFocus();
        }
    );

    return {
        init,
        addItems,
        students,
        isExpanded,
        toggleStudent,
        toggleAllStudents,
        reportLoaded,
        updateItemResponseScore,
        updateItemComment,
        updateAttemptComment,
        clearHasFocus,
    };
});
