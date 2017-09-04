-- An off-by-one bug in Evaluation_Response::assignQuestions() was causing a single response to
-- be inserted when a quiz did not have any questions. The question_id was 0.
DELETE FROM assess_response WHERE question_id = 0;
