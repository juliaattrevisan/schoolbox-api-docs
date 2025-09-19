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


-- Insert a new thingy for Learning Moments notifs
-- TODO maybe make a space for this after id 178, but am scared of ramifications for these ids

INSERT INTO msg_source
(`namespace`, `key`, `role_type`, `description`)
VALUES (
   (SELECT id FROM msg_namespace WHERE slug = 'moment' LIMIT 1),
   'submitted',
   'staff',
   'someone posted a learning moment that requires approval'
);
