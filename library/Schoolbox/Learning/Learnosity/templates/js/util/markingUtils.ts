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
 * Get the response for a student item question if available
 * @param {StudentItemQuestionResponse[]} itemResponses - the item responses to get the response from
 * @param {string} responseId - the response id to get the response for
 * @returns {StudentItemQuestionResponse} - the response
 */
function getItemResponse(itemResponses: StudentItemQuestionResponse[], responseId: string) {
    return itemResponses.find((itemResponse) => itemResponse.learnosityReference === responseId);
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
 * Loop through each question in the item and check for a score to determine if the question is already marked.
 * If there is no maxScore, we assume that the question is considered marked if the first question contains a comment.
 * So the first question comment covers isMarked for any questions within the item that don't have a maxScore
 * @param { StudentItem } item - the item to check
 */
export function setInitialQuestionIsMarked(item: StudentItem) {
    const firstQuestion = item.questions[0];
    item.questions.forEach((question) => {
        if (question.response) {
            const { response } = question;
            if (response?.maxScore === null) {
                response.isMarked =
                    (firstQuestion.response as StudentItemQuestionResponse).comment !== undefined &&
                    (firstQuestion.response as StudentItemQuestionResponse).comment !== null &&
                    (firstQuestion.response as StudentItemQuestionResponse).comment !== "";
            } else {
                response.isMarked = response.score !== null;
            }
        }
    });
}

/**
 * Whether the item is marked, loop through all questions and check if they are all marked
 * @param {StudentItem} item - the item to check
 * @returns {boolean} - whether the item is marked
 */
export function getItemIsMarked(item: StudentItem) {
    // if any questions don't have a response, then the item is not marked
    if (item.questions.some(($question) => !$question.response)) {
        return false;
    }
    return item.questions.every(($question) => ($question.response as StudentItemQuestionResponse).isMarked);
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

/**
 * Get the total item score
 * @param {StudentItem} item - the item to get the score from
 * @returns {number} - the total item score
 */
export function getItemScore(item: StudentItem) {
    // loop through all questions and add up the score
    return item.questions.reduce((total, question) => {
        if (question.response && question.response.score) {
            return total + question.response.score;
        }
        return total;
    }, 0);
}

/**
 * Get the total item max score
 * @param {StudentItem} item - the item to get the score from
 * @returns {number} - the total item max score
 */
export function getItemMaxScore(item: StudentItem) {
    // loop through all questions and add up the max score
    return item.questions.reduce((total, question) => {
        if (question.response && question.response?.maxScore) {
            return total + question.response.maxScore;
        }
        return total;
    }, 0);
}

/**
 * Get the current total score for all items, so the total of all questions within each item
 * @param {Student} student - the student to get the score from
 * @returns {number} - the total attempt score
 */
export function getAttemptScore(student: Student) {
    // loop through all student items and questions and add up the score
    return student.items.reduce((totalScoreAcc, item) => {
        const itemScore = item.questions.reduce((itemScoreAcc, question) => {
            if (question.response && question.response.score) {
                return itemScoreAcc + question.response.score;
            }
            return itemScoreAcc;
        }, 0);
        return totalScoreAcc + itemScore;
    }, 0);
}

/**
 * Get the max score for all items
 * @param {Student} student - the student to get the max score from
 * @returns {number} - the total attempt max score
 */
export function getAttemptMaxScore(student: Student) {
    // loop through all student items and questions and add up the max score
    return student.items.reduce((maxScoreAcc, item) => {
        const itemMaxScore = item.questions.reduce((itemMaxScoreAcc, question) => {
            if (question.response && question.response?.maxScore) {
                return itemMaxScoreAcc + question.response.maxScore;
            }
            return itemMaxScoreAcc;
        }, 0);
        return maxScoreAcc + itemMaxScore;
    }, 0);
}
