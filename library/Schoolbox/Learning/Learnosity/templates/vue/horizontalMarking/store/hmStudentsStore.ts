/**
 * Store the students, and link them to their reports and items
 */

import { defineStore } from "pinia";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";
import type { StudentAttemptComment } from "@schoolboxLearnosity/js/types/StudentAttemptComment";
import type { StudentUrls } from "@schoolboxLearnosity/js/types/StudentUrls";
import {
    getItemQuestions,
    getAttemptResponse,
    getItemIsMarked,
    getStudentIsMarked,
    setInitialQuestionIsMarked,
    getUrls,
} from "@schoolboxLearnosity/js/util/markingUtils";
import { useStore } from "./horizontalMarkingStore";
import { useItemsStore } from "./hmItemsStore";

export const useStudentsStore = defineStore("hmStudents", () => {
    const students = window.Vue.ref<Student[]>([]);
    const isExpanded = window.Vue.ref<boolean>(true); // if all students are expanded
    const itemResponses = window.Vue.ref<StudentItemQuestionResponse[]>([]); // all item responses from the backend
    const attemptComments = window.Vue.ref<StudentAttemptComment[]>([]); // all attempt comments from the backend
    const urls = window.Vue.ref<StudentUrls[]>([]); // the urls for each student to save score, comment and attempt comment
    const showMarkedAttempts = window.Vue.ref<boolean>(true); // if true, show attempts that have been marked
    const allStudentsMarked = window.Vue.computed(() => students.value.every((student) => student.isMarked)); // if true, all students have been marked
    const store = useStore();

    /**
     * store any passed through properties
     * @param {object} $students - the students
     * @param {StudentItemQuestionResponse[]} $itemResponses - the item responses
     * @param {StudentAttemptComment[]} $attemptComments - the attempt responses
     * @param {StudentUrls[]} $urls - the urls
     */
    function init(
        $students: Student[],
        $itemResponses: StudentItemQuestionResponse[],
        $attemptComments: StudentAttemptComment[],
        $urls: StudentUrls[]
    ) {
        $students.forEach((student) => {
            students.value.push({
                ...student,
                isReportLoaded: false,
                isExpanded: false,
                items: [],
            });
        });
        itemResponses.value = $itemResponses;
        attemptComments.value = $attemptComments;
        urls.value = $urls;
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
        const student = students.value.find(($student) => $student.elementId === reportId); // find the student that matches the report
        if (student) {
            const studentItems: StudentItem[] = [];
            items.forEach((item) => {
                // for each item, we only want to store the below properties, removing any others Learnosity may have provided
                studentItems.push({
                    reference: item.reference,
                    questions: getItemQuestions(itemResponses.value, item.questions),
                });
            });
            student.items = studentItems;
            // need to do after first loop as we rely on questions and responses being set first
            student.items.forEach((item) => {
                setInitialQuestionIsMarked(item);
                item.isMarked = getItemIsMarked(item);
            });
            student.isMarked = getStudentIsMarked(student);
            student.attemptComment = getAttemptResponse(attemptComments.value, student.id);
            student.urls = getUrls(urls.value, student.id);
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
     * For each item within the store, check if all students have been marked for that item
     */
    function updateItemsIsMarked() {
        useItemsStore().items.forEach((item) => {
            item.isMarked = students.value.every((student) => {
                const studentItem = student.items.find(($item) => $item.reference === item.reference);
                return studentItem ? studentItem.isMarked : false;
            });
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
        // check if all student reports are loaded and if so, the central store can be considered loaded
        store.isLoaded = students.value.every((student) => student.isReportLoaded);
        if (store.isLoaded) {
            updateItemsIsMarked();
        }
    }

    /**
     * To update the isMarked property of an item and student
     * @param {Student} student - the student to update
     * @param {StudentItem} item - the item to update
     */
    function updateIsMarked(student: Student, item: StudentItem) {
        item.isMarked = getItemIsMarked(item);
        student.isMarked = getStudentIsMarked(student);
        updateItemsIsMarked();
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
        (question.response as StudentItemQuestionResponse).score = score;
        (question.response as StudentItemQuestionResponse).isMarked = true;
        updateIsMarked(student, item);
    }

    /**
     * To update the comment of an item
     * @param {Student} student - the student to update
     * @param {StudentItem} item - the item to update
     * @param {StudentItemQuestion} question - the question to update
     * @param {string} comment - the comment to update
     */
    function updateItemComment(student: Student, item: StudentItem, question: StudentItemQuestion, comment: string) {
        (question.response as StudentItemQuestionResponse).comment = comment;
        // set all questions responses that don't have a maxScore as marked
        const questionsWithoutMaxScore = item.questions.filter(
            ($question) => ($question.response as StudentItemQuestionResponse)?.maxScore === null
        );
        questionsWithoutMaxScore.forEach(($question) => {
            ($question.response as StudentItemQuestionResponse).isMarked = true;
        });
        updateIsMarked(student, item);
    }

    /**
     * To update the overall comment response to an attempt
     * @param {Student} student - the student to update
     * @param {string} comment - the comment to update
     */
    function updateAttemptComment(student: Student, comment: string) {
        student.attemptComment.comment = comment;
    }

    return {
        init,
        addItems,
        students,
        isExpanded,
        showMarkedAttempts,
        allStudentsMarked,
        toggleStudent,
        toggleAllStudents,
        reportLoaded,
        updateItemResponseScore,
        updateItemComment,
        updateAttemptComment,
    };
});
