/** Update the unit to point to the new parten course folder that it has been moved into **/
UPDATE course_unit_specification cus
  JOIN course_specification cs ON cus.course_specification_id = cs.id
  JOIN folder cusfolder ON cus.folder_id = cusfolder.id
  JOIN folder csfolder ON cs.folder_id = csfolder.id
  JOIN folder cusparentfolder ON cusfolder.parent = cusparentfolder.id
  JOIN course_specification cs2 ON cusfolder.parent = cs2.folder_id
SET cus.course_specification_id = cs2.id
WHERE cusfolder.parent != csfolder.id and cusfolder.deleted_at is null and cus.deleted_at is null;


/** Insert new courses if unit has been moved into a location that does not contain a parent course **/

INSERT INTO course_specification (folder_id, created_at, updated_at) SELECT DISTINCT cusparentfolder.id, NOW(), NOW() FROM course_unit_specification cus
  JOIN course_specification cs ON cus.course_specification_id = cs.id
  JOIN folder cusfolder ON cus.folder_id = cusfolder.id
  JOIN folder csfolder ON cs.folder_id = csfolder.id
  JOIN folder cusparentfolder ON cusfolder.parent = cusparentfolder.id
WHERE cusfolder.parent != csfolder.id AND cusparentfolder.id != 0 and cusfolder.deleted_at is null and cus.deleted_at is null;

/** We run this twice now that we have inserted the new courses, we can point the moved units at them **/
UPDATE course_unit_specification cus
  JOIN course_specification cs ON cus.course_specification_id = cs.id
  JOIN folder cusfolder ON cus.folder_id = cusfolder.id
  JOIN folder csfolder ON cs.folder_id = csfolder.id
  JOIN folder cusparentfolder ON cusfolder.parent = cusparentfolder.id
  JOIN course_specification cs2 ON cusfolder.parent = cs2.folder_id
SET cus.course_specification_id = cs2.id
WHERE cusfolder.parent != csfolder.id and cusfolder.deleted_at is null and cus.deleted_at is null;