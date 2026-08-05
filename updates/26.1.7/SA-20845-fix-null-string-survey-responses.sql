# SA-20845: a bug in Evaluation_Answer_Radio/Multi/MultiOther::saveResponse()
# stored the literal string 'NULL' in assess_response.response instead of a
# real SQL NULL whenever a submission had no answer. This isn't valid
# serialized PHP data, causing unserialize() warnings on the Survey results
# and Evaluation export pages. The write-side bug is fixed in code; this
# repairs historical rows so they behave the same as legitimately-unanswered
# responses.
UPDATE `assess_response`
SET `response` = NULL
WHERE `response` = 'NULL';
