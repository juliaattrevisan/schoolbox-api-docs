/**
 * Store the students, and link them to their reports and items
 */

import { defineStore } from "pinia";
import { useStore } from "./horizontalMarkingStore";
import { useItemsStore } from "./hmItemsStore";
import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import type { StudentItemQuestion } from "../types/StudentItemQuestion";
import type { StudentItemQuestionResponse } from "../types/StudentItemQuestionResponse";
import type { StudentAttemptComment } from "../types/StudentAttemptComment";
import type { StudentUrls } from "../types/StudentUrls";

export const useStudentsStore = defineStore("hmStudents", () => {
    const students = window.Vue.ref<Student[]>([]);
    const isExpanded = window.Vue.ref<boolean>(true);

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
     * Get the response for a student item question
     * @param {string} responseId - the response id to get the response for
     * @returns {StudentItemQuestionResponse} - the response
     */
    function getItemResponse(responseId: string) {
        return useStore().itemResponses.find(
            (itemResponse) => itemResponse.learnosityReference === responseId
        ) as StudentItemQuestionResponse;
    }

    /**
     * Get the response for a student item linked to a question
     * @param {StudentItemQuestion} $questions - the questions to get the responses for
     * @returns {StudentItemQuestion[]} - the questions with linked responses
     */
    function getItemQuestions($questions: StudentItemQuestion[]) {
        // for each question, we also want to add in the relevant response
        const questions = $questions.map((question) => {
            const response = getItemResponse(question.response_id);
            return {
                ...question,
                response,
            };
        });
        return questions;
    }

    /**
     * Get the response for a student attempt
     * @param {number} studentId - the student id to get the response for
     * @returns {StudentAttemptComment} - the response attempt
     */
    function getAttemptResponse(studentId: number) {
        return useStore().attemptComments.find(
            (attemptComment) => attemptComment.studentId === studentId
        ) as StudentAttemptComment;
    }

    /**
     * Whether the item is marked
     * @param {StudentItem} item - the item to check
     * @returns {boolean} - whether the item is marked
     */
    function getIsMarked(item: StudentItem) {
        const allQuestionsMarked = item.questions.every(($question) => $question.response.score !== null);
        return allQuestionsMarked;
    }

    /**
     * Get the urls for the student
     * @param {number} studentId - the student id to get the response for
     * @returns {StudentUrls} - the urls
     */
    function getUrls(studentId: number) {
        return useStore().urls.find((urlsItem) => urlsItem.studentId === studentId) as StudentUrls;
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
                    questions: getItemQuestions(item.questions),
                });
            });
            selectedStudent.items = studentItems;
            // need to do after as we rely on questions and responses being set first
            selectedStudent.items.forEach((item) => {
                item.isMarked = getIsMarked(item);
            });
            selectedStudent.attemptComment = getAttemptResponse(selectedStudent.id);
            selectedStudent.urls = getUrls(selectedStudent.id);
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
        // check if all student reports are loaded and if so, set the central store to loaded
        if (students.value.every((student) => student.isReportLoaded)) {
            useStore().isLoaded = true;
        }
    }

    /**
     * To update the score of a response
     * @param {StudentItem} item - the item that contains the question to update
     * @param {StudentItemQuestion} question - the question to update
     * @param {number} score - the score to update
     */
    function updateResponseScore(item: StudentItem, question: StudentItemQuestion, score: number) {
        question.response.score = score;
        // loop through all questions and check if they are all marked
        // const allQuestionsMarked = item.questions.every(($question) => $question.response.score !== null);
        item.isMarked = getIsMarked(item);
    }

    /**
     * To update the comment of a response
     * @param {StudentItemQuestion} question - the question to update
     * @param {string} comment - the comment to update
     */
    function updateItemComment(question: StudentItemQuestion, comment: string) {
        question.response.comment = comment;
    }

    /**
     * To update the mark status of the item
     * @param {StudentItem} item - the item to update
     * @param {boolean} isMarked - the score to update
     */
    function updateItemIsMarked(item: StudentItem, isMarked: boolean) {
        item.isMarked = isMarked;
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
        updateResponseScore,
        updateItemComment,
        updateItemIsMarked,
        updateAttemptComment,
        clearHasFocus,
    };
});
