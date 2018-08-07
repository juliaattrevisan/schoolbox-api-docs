INSERT INTO msg_source (namespace, `key`, role_type, description) VALUES
/* only staff can moderate at this time */
(5, 'submitted', 'staff', 'somebody posts a news article you can moderate'),

/* anyone can submit, so anyone can be rejected */
(5, 'rejected', 'staff', 'a moderator rejects a news article you have submitted'),
(5, 'rejected', 'student', 'a moderator rejects a news article you have submitted'),
(5, 'rejected', 'parent', 'a moderator rejects a news article you have submitted');

INSERT INTO msg_route_perm
SELECT ms.id, r.id, 'MU,DI,IN,MO,EM', 'IN,MO,EM'
FROM groups r, msg_source ms
WHERE ms.namespace = 5
AND ms.key IN ('submitted', 'rejected');
