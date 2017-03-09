UPDATE assessment
  JOIN submission_box sb ON sb.assessment_id = assessment.id
SET assessment.open_date = assessment.publish_date, assessment.close_date=assessment.due_date
WHERE accept_submission = 0 AND assessment.publish_date is not null AND assessment.due_date is not null AND (assessment.publish_date != assessment.open_date OR assessment.due_date != assessment.close_date);
