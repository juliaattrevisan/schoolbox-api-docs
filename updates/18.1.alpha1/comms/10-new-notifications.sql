INSERT INTO msg_source (namespace, `key`, role_type, description) VALUES
/* if an article is audienced ONLY to a single homepage, with no modifiers, users with WRITE+ access can moderate */
(5, 'submitted', 'staff', 'somebody posts a news article you can moderate'),
(5, 'submitted', 'student', 'somebody posts a news article you can moderate'),
(5, 'submitted', 'parent', 'somebody posts a news article you can moderate'),

/* anyone can submit, so anyone can be rejected */
(5, 'moderated', 'staff', 'a moderator accepts/rejects a news article you have submitted'),
(5, 'moderated', 'student', 'a moderator accepts/rejects a news article you have submitted'),
(5, 'moderated', 'parent', 'a moderator accepts/rejects a news article you have submitted');

INSERT INTO msg_route_perm
SELECT ms.id, r.id, 'MU,DI,IN,MO,EM', 'IN,MO,EM'
FROM groups r, msg_source ms
WHERE ms.namespace = 5
AND ms.key IN ('submitted', 'moderated');

UPDATE msg_source SET description = 'somebody posts news'
WHERE namespace = 5
AND `key` = 'posted';
