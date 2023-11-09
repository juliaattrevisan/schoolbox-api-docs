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
    getUrls,
} from "@schoolboxLearnosity/js/util/markingUtils";
import { useStore } from "./quizFeedbackStore";
import { useItemsStore } from "./qfItemsStore";

export const useStudentStore = defineStore("qfStudent", () => {
    const student = window.Vue.ref<Student>({
        id: 0,
        firstname: "",
        lastname: "",
        name: "",
        elementId: "",
        hasReport: false,
        isReportLoaded: false,
        isExpanded: false,
        isMarked: false,
        items: [],
        attemptComment: {
            learnosityReference: "",
            studentId: 0,
        },
        urls: {
            studentId: 0,
            score: "",
            comment: "",
            attemptComment: "",
            teacherFeedback: "",
        },
    });
    const isExpanded = window.Vue.ref<boolean>(true); // if all students are expanded
    const itemResponses = window.Vue.ref<StudentItemQuestionResponse[]>([]); // all item responses from the backend
    const attemptComments = window.Vue.ref<StudentAttemptComment[]>([]); // all attempt comments from the backend
    const urls = window.Vue.ref<StudentUrls>(); // the urls for each student to save score, comment and attempt comment
    const showMarkedAttempts = window.Vue.ref<boolean>(true); // if true, show attempts that have been marked
    // const allStudentsMarked = window.Vue.computed(() => students.value.every((student) => student.isMarked)); // if true, all students have been marked
    const store = useStore();

    /**
     * store any passed through properties
     * @param {Student} $student - the student
     * @param {StudentItemQuestionResponse[]} $itemResponses - the item responses
     * @param {StudentAttemptComment} $attemptComment - the attempt responses
     * @param {StudentUrls} $urls - the urls
     */
    function init(
        $student: Student,
        $itemResponses: StudentItemQuestionResponse[],
        $attemptComment: StudentAttemptComment,
        $urls: StudentUrls
    ) {
        student.value = {
            ...$student,
            isReportLoaded: false,
            isExpanded: false,
            hasReport: false,
            items: [],
        };
        itemResponses.value = $itemResponses;
        // attemptComments.value = $attemptComments;
        urls.value = $urls;
    }

    /**
     * Loop through each question in the item and check for a score to determine if the question is already marked.
     * If there is no maxScore, we assume that the question is considered marked if the first question contains a comment.
     * So the first question comment covers isMarked for any questions within the item that don't have a maxScore
     * @param { StudentItem } item - the item to check
     */
    function setInitialQuestionIsMarked(item: StudentItem) {
        item.questions.forEach((question) => {
            if (question.response.maxScore === null) {
                question.response.isMarked =
                    item.questions[0].response.comment &&
                    item.questions[0].response.comment !== null &&
                    item.questions[0].response.comment !== "";
            } else {
                question.response.isMarked = question.response.score !== null;
            }
        });
    }

    /**
     * Add items to a student
     * Note that the items are stored per student to link them to their report
     * The itemsStore is used to reference all items within the quiz, and these are NOT linked to the student reports
     * @param {StudentItem[]} $items - the items to add
     */
    function addItems($items: StudentItem[]) {
        const items = $items.filter((item) => item.questions.length > 0); // remove any items that don't have questions
        const studentItems: StudentItem[] = [];
        items.forEach((item) => {
            // for each item, we only want to store the below properties, removing any others Learnosity may have provided
            studentItems.push({
                reference: item.reference,
                questions: getItemQuestions(itemResponses.value, item.questions),
            });
        });
        student.value.items = studentItems;
        // need to do after first loop as we rely on questions and responses being set first
        student.value.items.forEach((item) => {
            setInitialQuestionIsMarked(item);
            item.isMarked = getItemIsMarked(item);
        });
        student.value.isMarked = getStudentIsMarked(student.value);
        student.value.attemptComment = getAttemptResponse(attemptComments.value, student.value.id);
        student.value.urls = getUrls(urls.value, student.value.id);
    }

    /**
     * To show/hide a student
     * @param {Student} $student - the student to change
     */
    function toggleStudent($student: Student) {
        student.value.isExpanded = !$student.isExpanded;
        // if all students are now expanded, set the overall toggle to true
        // if (students.value.every(($student) => $student.isExpanded)) {
        //     isExpanded.value = true;
        // }
        // // if all students are now collapsed, set the overall toggle to false
        // else if (students.value.every(($student) => !$student.isExpanded)) {
        //     isExpanded.value = false;
        // }
    }

    /**
     * For each item within the store, check if all students have been marked for that item
     */
    function updateItemsIsMarked() {
        useItemsStore().items.forEach((item) => {
            const studentItem = student.value.items.find(($item) => $item.reference === item.reference) as StudentItem;
            item.isMarked = studentItem.isMarked;
        });
    }

    /**
     * To notify that a report linked to a student has been loaded
     */
    function reportLoaded() {
        student.value.isReportLoaded = true;
        // check if all student reports are loaded and if so, the central store can be considered loaded
        store.isLoaded = true;
        updateItemsIsMarked();
    }

    /**
     * To update the isMarked property of an item and student
     * @param {Student} $student - the student to update
     * @param {StudentItem} item - the item to update
     */
    function updateIsMarked($student: Student, item: StudentItem) {
        item.isMarked = getItemIsMarked(item);
        student.value.isMarked = getStudentIsMarked(student.value);
        updateItemsIsMarked();
    }

    /**
     * To update the score of a question
     * @param {Student} $student - the student to update
     * @param {StudentItem} item - the item that contains the question to update
     * @param {StudentItemQuestion} question - the question to update
     * @param {number} score - the score to update
     */
    function updateItemResponseScore(
        $student: Student,
        item: StudentItem,
        question: StudentItemQuestion,
        score: number
    ) {
        question.response.score = score;
        question.response.isMarked = true;
        updateIsMarked($student, item);
    }

    /**
     * To update the comment of an item
     * @param {Student} $student - the student to update
     * @param {StudentItem} item - the item to update
     * @param {StudentItemQuestion} question - the question to update
     * @param {string} comment - the comment to update
     */
    function updateItemComment($student: Student, item: StudentItem, question: StudentItemQuestion, comment: string) {
        question.response.comment = comment;
        // set all questions responses that don't have a maxScore as marked
        const questionsWithoutMaxScore = item.questions.filter(($question) => $question.response.maxScore === null);
        questionsWithoutMaxScore.forEach(($question) => {
            $question.response.isMarked = true;
        });
        updateIsMarked($student, item);
    }

    /**
     * To update the overall comment response to an attempt
     * @param {Student} $student - the student to update
     * @param {string} comment - the comment to update
     */
    function updateAttemptComment($student: Student, comment: string) {
        $student.attemptComment.comment = comment;
    }

    return {
        init,
        addItems,
        student,
        isExpanded,
        showMarkedAttempts,
        // allStudentsMarked,
        toggleStudent,
        reportLoaded,
        updateItemResponseScore,
        updateItemComment,
        updateAttemptComment,
    };
});
