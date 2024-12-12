# Remove code from imported curricula.
UPDATE curriculum_node SET code = '' WHERE uuid = 'b05efd5f-3ef1-45e7-8369-6b1d200b24bd';

# Remove code from existing assessments, folders, and rubrics.
SET @curriculumNodeId = (SELECT id FROM curriculum_node WHERE uuid = 'b05efd5f-3ef1-45e7-8369-6b1d200b24bd');
DELETE FROM curriculum_node_for_assessment        WHERE curriculum_node_id = @curriculumNodeId;
DELETE FROM curriculum_node_for_category          WHERE curriculum_node_id = @curriculumNodeId;
DELETE FROM curriculum_node_for_rubric_capability WHERE curriculum_node_id = @curriculumNodeId;
DELETE FROM curriculum_node_for_rubric_indicator  WHERE curriculum_node_id = @curriculumNodeId;
