<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Relation\CommandLineTool;

use Schoolbox\Application\CliTool\App;

/**
 * Tool for reloading the data within the assessment relation materialized view
 * `mv_assessment_relation`.
 */
class Reloader extends App
{
    /**
     * SQL for selecting all assessment relations.
     *
     * (This mirrors the CREATE VIEW statement for `v_assessment_relation`.)
     */
    public const SELECT_ASSESSMENT_RELATION_SQL = "
        select `a`.`id` AS `assessment_id`,
               `f`.`id` AS `folder_id`,
               NULL     AS `project_assessment_id`,
               NULL     AS `course_implementation_id`,
               NULL     AS `course_unit_implementation_id`,
               NULL     AS `course_specification_id`,
               NULL     AS `course_unit_specification_id`
        from ((`assessment` `a`
            join `assessment_folder` `af` on (`a`.`id` = `af`.`assessment_id`))
            join `folder` `f` on (`af`.`folder_id` = `f`.`id`))
        union
        select `a`.`id`   AS `assessment_id`,
               `f`.`id`   AS `folder_id`,
               NULL       AS `project_assessment_id`,
               `ci`.`id`  AS `course_implementation_id`,
               `cui`.`id` AS `course_unit_implementation_id`,
               NULL       AS `course_specification_id`,
               NULL       AS `course_unit_specification_id`
        from ((((`assessment` `a`
            join `assessment_course_unit_implementation` `acui` on (`a`.`id` = `acui`.`assessment_id`))
            join `course_unit_implementation` `cui` on (`acui`.`course_unit_implementation_id` = `cui`.`id`))
            join `course_implementation` `ci` on (`cui`.`course_implementation_id` = `ci`.`id`))
            join `folder` `f` on (`ci`.`folder_id` = `f`.`id`))
        union
        select `a`.`id`   AS `assessment_id`,
               `f`.`id`   AS `folder_id`,
               NULL       AS `project_assessment_id`,
               NULL       AS `course_implementation_id`,
               NULL       AS `course_unit_implementation_id`,
               `cs`.`id`  AS `course_specification_id`,
               `cus`.`id` AS `course_unit_specification_id`
        from ((((`assessment` `a`
            join `assessment_course_unit_specification` `acus` on (`a`.`id` = `acus`.`assessment_id`))
            join `course_unit_specification` `cus` on (`acus`.`course_unit_specification_id` = `cus`.`id`))
            join `course_specification` `cs` on (`cus`.`course_specification_id` = `cs`.`id`))
            join `folder` `f` on (`cs`.`folder_id` = `f`.`id`))
        union
        select `a`.`id` AS `assessment_id`,
               `f`.`id` AS `folder_id`,
               `p`.`id` AS `project_assessment_id`,
               NULL     AS `course_implementation_id`,
               NULL     AS `course_unit_implementation_id`,
               NULL     AS `course_specification_id`,
               NULL     AS `course_unit_specification_id`
        from ((((`assessment` `a`
            join `project_assessment` `pa` on (`a`.`id` = `pa`.`assessment_id`))
            join `project` `p` on (`pa`.`project_id` = `p`.`id`))
            join `assessment_folder` `af` on (`p`.`assessment_id` = `af`.`assessment_id`))
            join `folder` `f` on (`af`.`folder_id` = `f`.`id`))
        union
        select `a`.`id`   AS `assessment_id`,
               `f`.`id`   AS `folder_id`,
               `p`.`id`   AS `project_assessment_id`,
               `ci`.`id`  AS `course_implementation_id`,
               `cui`.`id` AS `course_unit_implementation_id`,
               NULL       AS `course_specification_id`,
               NULL       AS `course_unit_specification_id`
        from ((((((`assessment` `a`
            join `project_assessment` `pa` on (`a`.`id` = `pa`.`assessment_id`))
            join `project` `p` on (`pa`.`project_id` = `p`.`id`))
            join `assessment_course_unit_implementation` `acui` on (`p`.`assessment_id` = `acui`.`assessment_id`))
            join `course_unit_implementation` `cui` on (`acui`.`course_unit_implementation_id` = `cui`.`id`))
            join `course_implementation` `ci` on (`cui`.`course_implementation_id` = `ci`.`id`))
            join `folder` `f` on (`ci`.`folder_id` = `f`.`id`))
        union
        select `a`.`id`   AS `assessment_id`,
               `f`.`id`   AS `folder_id`,
               `p`.`id`   AS `project_assessment_id`,
               NULL       AS `course_implementation_id`,
               NULL       AS `course_unit_implementation_id`,
               `cs`.`id`  AS `course_specification_id`,
               `cus`.`id` AS `course_unit_specification_id`
        from ((((((`assessment` `a`
            join `project_assessment` `pa` on (`a`.`id` = `pa`.`assessment_id`))
            join `project` `p` on (`pa`.`project_id` = `p`.`id`))
            join `assessment_course_unit_specification` `acus` on (`p`.`assessment_id` = `acus`.`assessment_id`))
            join `course_unit_specification` `cus` on (`acus`.`course_unit_specification_id` = `cus`.`id`))
            join `course_specification` `cs` on (`cus`.`course_specification_id` = `cs`.`id`))
            join `folder` `f` on (`cs`.`folder_id` = `f`.`id`))
        order by `assessment_id`;
    ";

    /**
     * @return void
     */
    public function run(): void
    {
        $db = $this->getDatabaseInternal();

        // Delete
        if ($this->perform()) {
            $this->getLogger()->notice('Deleting from mv_assessment_relation...');
            $deleteQuery = $db->prepareQuery('DELETE FROM mv_assessment_relation');
            $deleteQuery->execute();
            $deleteCount = $deleteQuery->rowCount();
            $this->getLogger()->notice(
                sprintf('Deleted %d mv_assessment_relation records', $deleteCount)
            );
        } else {
            $deleteCountQuery = $db->prepareQuery('SELECT COUNT(*) FROM mv_assessment_relation');
            $deleteCountQuery->execute();
            $deleteCount = (int)$deleteCountQuery->fetchColumn();
            $this->getLogger()->notice(
                sprintf('Would have deleted %d mv_assessment_relation records', $deleteCount)
            );
        }

        // Re-insert
        if ($this->perform()) {
            $this->getLogger()->notice('Re-inserting into mv_assessment_relation...');
            $insertQuery = $db->prepareQuery(
                'INSERT INTO mv_assessment_relation ' . self::SELECT_ASSESSMENT_RELATION_SQL
            );
            $insertQuery->execute();
            $insertCount = $insertQuery->rowCount();
            $this->getLogger()->notice(
                sprintf('Inserted %d mv_assessment_relation records', $insertCount)
            );
        } else {
            $selectQuery = $db->prepareQuery(self::SELECT_ASSESSMENT_RELATION_SQL);
            $selectQuery->execute();
            $insertCount = $selectQuery->rowCount();
            $this->getLogger()->notice(
                sprintf('Would have inserted %d mv_assessment_relation records', $insertCount)
            );
        }

        if ($insertCount !== $deleteCount) {
            $this->getLogger()->warning(
                'Delete and insert counts do not match: mv_assessment_relation needs to be reviewed.'
            );
        }
    }
}
