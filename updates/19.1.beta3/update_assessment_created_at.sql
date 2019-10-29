-- Any assessments that reside in a unit-impl have their created_at dates updated to not be prior to unit-impl creation date.
UPDATE assessment, assessment_course_unit_implementation, course_unit_implementation
SET assessment.created_at = course_unit_implementation.created_at
WHERE assessment.id=assessment_course_unit_implementation.assessment_id
  AND assessment_course_unit_implementation.course_unit_implementation_id = course_unit_implementation.id
  AND assessment.created_at < course_unit_implementation.created_at;
