# SA-20845: a bug in Evaluation_Answer_Radio/Multi/MultiOther::saveResponse()
# stored the literal string 'NULL' in assess_response.response instead of a
# real SQL NULL whenever a submission had no answer. This isn't valid
# serialized PHP data, causing unserialize() warnings on the Survey results
# and Evaluation export pages. The write-side bug is fixed in code; this
# repairs historical rows so they behave the same as legitimately-unanswered
# responses.
#
# assess_response.response is shared by every question type, including
# free-text ones (e.g. Evaluation_Answer_Short) that store raw user input
# verbatim. This must only touch rows belonging to the three affected
# question types, and must compare case-sensitively (assess_response uses
# utf8mb4_unicode_ci, so an unscoped/case-insensitive match would also wipe
# a legitimate free-text answer of "null"/"Null"/etc).
UPDATE `assess_response` ar
JOIN `assess_question` aq ON aq.question_id = ar.question_id
JOIN `assess_luQuestionType` aqt ON aqt.type_id = aq.type_id
SET ar.response = NULL
WHERE aqt.classname IN ('Radio', 'Multi', 'MultiOther')
  AND ar.response = BINARY 'NULL';
