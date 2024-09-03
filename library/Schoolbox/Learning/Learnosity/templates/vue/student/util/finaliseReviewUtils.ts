/**
 * Any finalise review related methods that are used by both vertical (quiz-feedback.bue) and horizontal marking
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";

/**
 * This ensures the mark is the latest valid submission.
 * If the user is quick enough, they can update the mark and press submit before
 * the mark is submitted to the back end.
 * @param student - the student to get the mark from
 * @returns - the mark to submit
 */
export function getLatestMark(student: Student): string {
    const gradeContainer = document.getElementById(`grade-container-${student.id}`) as HTMLDivElement;
    const formElement = gradeContainer.querySelector("input,select") as HTMLFormElement; // depending on the Mark Type, the mark field can either be an input or a select
    if (gradeContainer === null || formElement === null) {
        return student.attemptResponse?.mark ?? "";
    }
    if (formElement.getAttribute("aria-invalid") === "false" && student.attemptResponse?.mark !== formElement.value) {
        return formElement.value as string;
    }
    return student.attemptResponse?.mark ?? "";
}

/**
 * To mark an attempt as reviewed
 * @param student - the student to mark
 * @returns - the data to send through
 */
export function getFinaliseReviewFormData(student: Student): FormData {
    const formData = new FormData();
    formData.append("responseId", student.attemptResponse?.responseId ?? "");
    formData.append("maxMarks", student.attemptResponse?.maxMarks?.toString() ?? "");
    formData.append("mark", getLatestMark(student));
    formData.append("comment", student.attemptResponse?.comment ?? "");
    formData.append("publish", "1");
    return formData;
}
