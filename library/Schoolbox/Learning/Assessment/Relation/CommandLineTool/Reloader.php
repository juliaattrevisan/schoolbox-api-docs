<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Relation\CommandLineTool;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;

/**
 * Tool for reloading the data within the assessment relation materialized view
 * `mv_assessment_relation`.
 */
class Reloader extends App
{
    /**
     * Help options
     */
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

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
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }

    /**
     * @inheritdoc
     *
     * @return never-return exit `CliKernelAbstract::EXIT_STATUS_APP_ERROR` if
     *                      there's a data issue detected, otherwise exits with
     *                      `CliKernelAbstract::EXIT_STATUS_OK`
     */
    public function run(): void
    {
        // Help output
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->outputHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $db = $this->getDatabaseInternal();

        // Delete
        if ($this->perform()) {
            $deleteQuery = $db->prepareQuery('DELETE FROM mv_assessment_relation');
            $deleteQuery->execute();
            $deleteCount = $deleteQuery->rowCount();
            if ($deleteCount > 0) {
                $this->getLogger()->notice(
                    sprintf('Deleted %d mv_assessment_relation records', $deleteCount)
                );
            }
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
            $this->getLogger()->notice('Inserting into mv_assessment_relation...');
            $insertQuery = $db->prepareQuery(
                'SET STATEMENT max_statement_time=0 FOR INSERT INTO mv_assessment_relation '
                . self::SELECT_ASSESSMENT_RELATION_SQL,
            );
            $insertQuery->execute();
            $insertCount = $insertQuery->rowCount();
            $this->getLogger()->notice(
                sprintf('Inserted %d mv_assessment_relation records', $insertCount)
            );
        } else {
            $selectQuery = $db->prepareQuery(
                'SET STATEMENT max_statement_time=0 FOR '
                . self::SELECT_ASSESSMENT_RELATION_SQL,
            );
            $selectQuery->execute();
            $insertCount = $selectQuery->rowCount();
            $this->getLogger()->notice(
                sprintf('Would have inserted %d mv_assessment_relation records', $insertCount)
            );
        }

        // Raise a warning, if:
        // the insert and delete count don't match, and the table wasn't empty
        // (if the table was empty, this was an initial load of assessment
        //  relation data)
        if ($deleteCount > 0 && $insertCount !== $deleteCount) {
            $this->getLogger()->warning(
                'Delete and insert counts do not match: recommend running report `./tools/assessment/relations-report.php` for further diagnosis.'
            );
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        exit(CliKernelAbstract::EXIT_STATUS_OK);
    }

    /**
     * Output help text
     *
     * @return void
     */
    private function outputHelp(): void
    {
        $file   = $this->getToolFilename();
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;
        echo <<<HELP

{$white}NAME:
    Assessment Relations Reloader

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    This tool reloads assessment relations, as stored in the
    `mv_assessment_relation` table.

    If the number of deleted and inserted assessment relations do not match,
    then there may be a data issue: this tool will suggest to run the reporting
    tool `./tools/assessment/relations-report.php` for further diagnosis (and
    raise an error code)

OPTIONS
    $yellow-h, --help$white
        Output this help text and exit.

    $yellow-v, -vv, -vvv$white
        Control verbosity.

    $yellow-p, --perform$white
        Actually perform the reload: otherwise, only output how many results
        would be deleted and inserted.


HELP;
    }

    /**
     * @inheritDoc
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
}
