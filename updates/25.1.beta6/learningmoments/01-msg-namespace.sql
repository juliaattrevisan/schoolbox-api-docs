-- rename contextual usage of "evidence" to "moment
-- evidence is/are the media attachment(s) that prove that a learning moment occurred

UPDATE msg_namespace
SET slug = 'moment'
WHERE slug = 'evidence'
LIMIT 1;

UPDATE msg_namespace
SET slug = 'moment_comment'
WHERE slug = 'evidence_comment'
LIMIT 1;

