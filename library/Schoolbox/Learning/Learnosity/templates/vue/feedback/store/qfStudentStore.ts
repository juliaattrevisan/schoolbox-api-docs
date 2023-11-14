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
import { getItemQuestions, getItemIsMarked, getStudentIsMarked } from "@schoolboxLearnosity/js/util/markingUtils";
import { useStore } from "./quizFeedbackStore";

export const useStudentStore = defineStore("qfStudent", () => {
    const student = window.Vue.ref<Student>({
        id: 0,
        firstname: "",
        lastname: "",
        name: "",
        elementId: "",
        hasReport: false,
        isReportLoaded: false,
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
    const itemResponses = window.Vue.ref<StudentItemQuestionResponse[]>([]); // all item responses from the backend
    const showMarkedAttempts = window.Vue.ref<boolean>(true); // if true, show attempts that have been marked
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
            hasReport: false,
            items: [],
            attemptComment: $attemptComment,
            urls: $urls,
            showMarked: true,
        };
        itemResponses.value = $itemResponses;
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
                title: item.title,
            });
        });
        student.value.items = studentItems;
        // need to do after first loop as we rely on questions and responses being set first
        student.value.items.forEach((item) => {
            setInitialQuestionIsMarked(item);
            item.isMarked = getItemIsMarked(item);
        });
        student.value.isMarked = getStudentIsMarked(student.value);
    }

    /**
     * To notify that a report linked to a student has been loaded
     */
    function reportLoaded() {
        student.value.isReportLoaded = true;
        store.isLoaded = true;
    }

    /**
     * To update the isMarked property of an item and student
     * @param {Student} $student - the student to update
     * @param {StudentItem} item - the item to update
     */
    function updateIsMarked($student: Student, item: StudentItem) {
        item.isMarked = getItemIsMarked(item);
        student.value.isMarked = getStudentIsMarked(student.value);
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
        showMarkedAttempts,
        reportLoaded,
        updateItemResponseScore,
        updateItemComment,
        updateAttemptComment,
    };
});
