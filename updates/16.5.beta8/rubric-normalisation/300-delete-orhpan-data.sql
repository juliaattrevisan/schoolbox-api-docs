
# -- Delete Orphan data to enable foreign keys
#
#
# -- DELETE marks when:
# -- * their descriptor does not exist
# -- * mark is already marked as invalid
# DELETE FROM rubric_mark WHERE id IN (
#   SELECT * FROM (
#   SELECT id
#   FROM rubric_mark
#   WHERE valid IS FALSE
#   AND descriptor_id NOT IN (SELECT DISTINCT id FROM rubric_descriptor)) t
# );
#
#
# -- DELETE marks when:
# -- * their descriptor does not exist
# -- * their subBox is not one of the usages of their criterion
# DELETE FROM rubric_mark WHERE id IN (
#   SELECT * FROM (
#   SELECT rm.id FROM rubric_mark rm
#   LEFT JOIN rubric_criterion rc ON rc.id = rm.criterion_id
#   LEFT JOIN rubric_usage ru ON ru.criterion_id = rc.id AND ru.submission_box_id = rm.submission_box_id
#   WHERE rm.descriptor_id NOT IN (SELECT DISTINCT id FROM rubric_descriptor)
#   AND ru.submission_box_id IS NULL) t
# );
#
#
# -- Delete descriptors and levels if they are linked (both directly and indirectly via `rubric_level`) to sub boxes that don't exist
# -- These *should* be safe to delete, they are logically the result of deleting a sub box prior to intro of soft-deletion.
# CREATE TABLE temp_descriptors_and_levels(
#   rd_id INT(11) UNSIGNED,
#   rl_id INT(11) UNSIGNED
# );
# -- Get all descriptors and levels that are both linked to the same sub box, but the sub box doesn't exist.
# INSERT INTO temp_descriptors_and_levels (
#   SELECT rd.id, rl.id
#   FROM `rubric_descriptor` rd
#   JOIN `rubric_level` rl ON rl.id = rd.level_id
#   WHERE rd.submission_box_id = rl.box_id
#   AND rd.submission_box_id NOT IN (SELECT DISTINCT id FROM submission_box)
# );
# -- Delete orphan descriptors
# DELETE FROM `rubric_descriptor` WHERE `id` IN (
#   SELECT DISTINCT rd_id FROM temp_descriptors_and_levels
# );
# -- Delete orphan levels
# DELETE FROM `rubric_level` WHERE `id` IN (
#   SELECT DISTINCT rl_id FROM temp_descriptors_and_levels
# );
# -- Drop temp table
# DROP TABLE temp_descriptors_and_levels;
#
#
# -- Delete descriptors if they are not linked to a criterion AND have no marks
# -- (the latter part is technically overkill, but just a safety margin in case to retain ability to recover student marks)
# DELETE FROM `rubric_descriptor` WHERE `id` IN (
#   SELECT * FROM (
#     SELECT rd.id
#     FROM rubric_descriptor rd
#     LEFT JOIN rubric_criterion rc ON rc.id = rd.criterion_id
#     LEFT JOIN rubric_mark rm ON rm.descriptor_id = rd.id
#     WHERE rc.id IS NULL
#     AND rm.id IS NULL) t
# );
#
#
# -- DELETE descriptors if belonging to non-existent levels, and not linked to any student marks
# DELETE FROM rubric_descriptor WHERE id IN (
#   SELECT * FROM (
#     SELECT rd.id
#     FROM rubric_descriptor rd
#     LEFT JOIN rubric_level rl ON rd.level_id = rl.id
#     LEFT JOIN rubric_mark rm ON rm.descriptor_id = rd.id
#     WHERE rl.id IS NULL
#     AND rm.id IS NULL) t
# );
#
#
#