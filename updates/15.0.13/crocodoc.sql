/* copies crocodoc uuid from teacher to student when there is a return to the student */

UPDATE submission_return sr
  JOIN student_submission ss
    ON ss.box_id = sr.box_id AND ss.owner = sr.owner
SET sr.uuid = ss.uuid
WHERE sr.uuid is null AND ss.uuid is not null;