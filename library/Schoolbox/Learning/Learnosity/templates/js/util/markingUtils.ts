/**
 * Various marking utilities to help with marking, primarily to merge Learnosity data with Schoolbox data
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import type { StudentItemQuestion } from "@schoolboxLearnosity/js/types/StudentItemQuestion";
import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";
import type { StudentAttemptComment } from "@schoolboxLearnosity/js/types/StudentAttemptComment";
import type { StudentUrls } from "@schoolboxLearnosity/js/types/StudentUrls";

/**
 * Get the response for a student item question
 * @param {StudentItemQuestionResponse[]} itemResponses - the item responses to get the response from
 * @param {string} responseId - the response id to get the response for
 * @returns {StudentItemQuestionResponse} - the response
 */
function getItemResponse(itemResponses: StudentItemQuestionResponse[], responseId: string) {
    return itemResponses.find(
        (itemResponse) => itemResponse.learnosityReference === responseId
    ) as StudentItemQuestionResponse;
}

/**
 * Get the response for a student item linked to a question
 * @param {StudentItemQuestionResponse[]} itemResponses - the item responses to get the response from
 * @param {StudentItemQuestion} $questions - the questions to get the responses for
 * @returns {StudentItemQuestion[]} - the questions with linked responses
 */
export function getItemQuestions(itemResponses: StudentItemQuestionResponse[], $questions: StudentItemQuestion[]) {
    // for each question, we also want to add in the relevant response
    const questions = $questions.map((question) => {
        const response = getItemResponse(itemResponses, question.response_id);
        return {
            ...question,
            response,
        };
    });
    return questions;
}

/**
 * Get the response for a student attempt
 * @param {StudentAttemptComment[]} attemptComments - the attempt comments to get the response from
 * @param {number} studentId - the student id to get the response for
 * @returns {StudentAttemptComment} - the response attempt
 */
export function getAttemptResponse(attemptComments: StudentAttemptComment[], studentId: number) {
    return attemptComments.find((attemptComment) => attemptComment.studentId === studentId) as StudentAttemptComment;
}

/**
 * Whether the item is marked, loop through all questions and check if they are all marked
 * @param {StudentItem} item - the item to check
 * @returns {boolean} - whether the item is marked
 */
export function getItemIsMarked(item: StudentItem) {
    return item.questions.every(($question) => $question.response.isMarked);
}

/**
 * Whether the student is marked, loop through all items and check if they are all marked
 * @param {Student} student - the student to check
 * @returns {boolean} - whether the student is marked
 */
export function getStudentIsMarked(student: Student) {
    return student.items.every((item) => item.isMarked);
}

/**
 * Get the urls for the student
 * @param {StudentUrls[]} urls - the urls to get the response from
 * @param {number} studentId - the student id to get the response for
 * @returns {StudentUrls} - the urls
 */
export function getUrls(urls: StudentUrls[], studentId: number) {
    return urls.find((urlsItem) => urlsItem.studentId === studentId) as StudentUrls;
}
