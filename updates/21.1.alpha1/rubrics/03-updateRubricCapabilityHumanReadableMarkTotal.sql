-- Total mark capability marks: just set to the same as value
UPDATE     rubric_capability_mark  rcm
INNER JOIN submission_return       sr  ON rcm.return_id = sr.id
INNER JOIN rubric_capability_usage rcu ON rcm.rubric_capability_id = rcu.rubric_capability_id AND sr.box_id = rcu.submission_box_id
INNER JOIN submission_box          sb  ON sr.box_id = sb.id
INNER JOIN submission_mark_type    smt ON sb.rubric_capability_mark_type_id = smt.mark_type_id

SET rcm.human_readable_value = ROUND(rcm.value, 2)

WHERE smt.slug = 'raw';
