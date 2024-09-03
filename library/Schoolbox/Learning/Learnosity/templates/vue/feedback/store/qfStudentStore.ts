/**
 * Store the students, and link them to their reports and items
 */

import { defineStore } from "pinia";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";
import type { StudentAttemptResponse } from "@schoolboxLearnosity/js/types/StudentAttemptResponse";
import type { StudentUrls } from "@schoolboxLearnosity/js/types/StudentUrls";
import {
    getItemQuestions,
    getItemIsMarked,
    getStudentIsMarked,
    setInitialQuestionIsMarked,
} from "@schoolboxLearnosity/js/util/markingUtils";
import { useStore } from "./quizFeedbackStore";

export const useStudentStore = defineStore("qfStudent", () => {
    const student = window.Vue.ref<Student>({
        id: 0,
        firstname: "",
        lastname: "",
        name: "",
        elementId: "",
        isReportLoaded: false,
        isMarked: false,
        items: [],
        attemptResponse: {
            learnosityReference: "",
            studentId: 0,
        },
        urls: {
            studentId: 0,
            postFeedbackScore: "",
            postFeedbackComment: "",
            postFeedbackAttemptComment: "",
            postFeedbackAttemptScore: "",
            getFeedback: "",
            postFinaliseAttempt: "",
        },
    });
    const itemResponses = window.Vue.ref<StudentItemQuestionResponse[]>([]); // all item responses from the backend
    const store = useStore();

    /**
     * store any passed through properties
     * @param $student - the student
     * @param $itemResponses - the item responses
     * @param $attemptResponse - the attempt response
     * @param $urls - the urls
     * @param $isReviewed - whether the attempt has been reviewed
     */
    function init(
        $student: Student,
        $itemResponses: StudentItemQuestionResponse[],
        $attemptResponse: StudentAttemptResponse,
        $urls?: StudentUrls,
        $isReviewed?: boolean,
    ): void {
        student.value = {
            ...$student,
            isReportLoaded: false,
            items: [],
            attemptResponse: $attemptResponse,
            urls: $urls === undefined ? undefined : $urls,
            showMarked: true,
            isReviewed: $isReviewed === undefined ? undefined : $isReviewed,
        };
        itemResponses.value = $itemResponses;
    }

    /**
     * Add items to a student
     * Note that the items are stored per student to link them to their report
     * The itemsStore is used to reference all items within the quiz, and these are NOT linked to the student reports
     * @param $items - the items to add
     */
    function addItems($items: StudentItem[]): void {
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
    function reportLoaded(): void {
        student.value.isReportLoaded = true;
        store.isLoaded = true;
    }

    /**
     * To update the isMarked property of an item and student
     * @param item - the item to update
     */
    function updateIsMarked(item: StudentItem): void {
        item.isMarked = getItemIsMarked(item);
        student.value.isMarked = getStudentIsMarked(student.value);
    }

    /**
     * To update the score of a question
     * @param item - the item that contains the question to update
     * @param question - the question to update
     * @param score - the score to update
     */
    function updateItemResponseScore(item: StudentItem, question: StudentItemQuestion, score: number): void {
        (question.response as StudentItemQuestionResponse).score = score;
        (question.response as StudentItemQuestionResponse).isMarked = true;
        updateIsMarked(item);
    }

    /**
     * To update the comment of an item
     * @param item - the item to update
     * @param question - the question to update
     * @param comment - the comment to update
     */
    function updateItemComment(item: StudentItem, question: StudentItemQuestion, comment: string): void {
        (question.response as StudentItemQuestionResponse).comment = comment;
        // set all questions responses that don't have a maxScore as marked
        const questionsWithoutMaxScore = item.questions.filter(
            ($question) => ($question.response as StudentItemQuestionResponse)?.maxScore === null,
        );
        questionsWithoutMaxScore.forEach(($question) => {
            ($question.response as StudentItemQuestionResponse).isMarked = true;
        });
        updateIsMarked(item);
    }

    /**
     * To update the overall comment response to an attempt
     * @param $student - the student to update
     * @param comment - the comment to update
     */
    function updateAttemptComment($student: Student, comment: string): void {
        $student.attemptResponse.comment = comment;
    }

    /**
     * To update the overall mark response to an attempt
     * @param $student - the student to update
     * @param mark - the mark to update
     */
    function updateAttemptMark($student: Student, mark: string): void {
        $student.attemptResponse.mark = mark;
    }

    return {
        init,
        addItems,
        student,
        reportLoaded,
        updateItemResponseScore,
        updateItemComment,
        updateAttemptComment,
        updateAttemptMark,
    };
});
