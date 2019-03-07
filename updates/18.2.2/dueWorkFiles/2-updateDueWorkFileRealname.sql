UPDATE     submission_box_file sbf
INNER JOIN storage             s   ON sbf.storage_id = s.hash
SET        sbf.realname = s.filename;
