alter table assess_attempt
    add mark decimal(15, 2) null;

alter table assess_attempt
    add max_mark decimal(15, 2) null;


### update quiz marks to new location/ leaving them were they currently are
UPDATE assess_attempt
SET assess_attempt.mark = (
    SELECT
SUM(ar.mark)  AS mark
    FROM assess_attempt aa
             JOIN assess q ON q.assess_id = aa.assess_id
             LEFT JOIN assess_response ar ON aa.attempt_id = ar.attempt_id
             LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
    WHERE aa.attempt_id = assess_attempt.attempt_id),

    assess_attempt.max_mark = (
        SELECT
SUM(aq.marks) AS max_mark
        FROM assess_attempt aa
                 JOIN assess q ON q.assess_id = aa.assess_id
                 LEFT JOIN assess_response ar ON aa.attempt_id = ar.attempt_id
                 LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
        WHERE aa.attempt_id = assess_attempt.attempt_id)

where assess_attempt.mark is null
