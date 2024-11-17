<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Course\CommandLineTool;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;

/**
 * Detect and optionally repair duplicate units within course imports.
 */
class RepairDuplicateUnits extends App
{
    // Short and long help options
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP = 'help';

    // Short and long folder options
    private const OPT_SHORT_FOLDER = 'f:';
    private const OPT_LONG_FOLDER = 'folder:';

    // Which repair strategy to use? One of either:
    // * delete assessments
    // * move assessments out of unit
    // * attempt to move into the newest copy of the unit?
    private const OPT_LONG_STRATEGY = 'strategy:';

    /**
     * Query for finding the IDs of duplicate unit specs in a single
     * implementation.
     */
    private const SELECT_DUPLICATE_UNITS_QUERY = <<<SQL
        SELECT
            cui.course_implementation_id,
            cui.course_unit_specification_id,
            count(*) AS c
        FROM course_unit_implementation cui
        INNER JOIN course_implementation ci ON cui.course_implementation_id = ci.id
        WHERE cui.deleted_at IS NULL
        -- add folder checks
        GROUP BY
            cui.course_implementation_id,
            cui.course_unit_specification_id
        HAVING c > 1;
    SQL;

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_FOLDER,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FOLDER,
        ];
    }


    /**
     * @return void
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            $this->printHelp();
        }

        $db    = $this->getDatabaseInternal();
        $log   = $this->getLogger();
        $query = $db->prepare(self::SELECT_DUPLICATE_UNITS_QUERY);
        $query->execute();
        $log->notice(sprintf('%s duplicate units found.', $query->rowCount()));
        foreach ($query as $row) {

        }

        exit(CliKernelAbstract::EXIT_STATUS_OK);
    }

    /**
     * Print help and exit
     *
     * @return never-return
     */
    private function printHelp(): void
    {
        $file = $this->getToolFilename();

        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        echo <<<HELP
NAME:
    Repair duplicate units tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION:
    Detect and optionally repair units which somehow exist more than once within the same course import in the same
    class. This is considered broken data, because it can prevent the course import from being modified.

    This neither detects nor repairs courses which have been imported more than once into a class: that is not
    considered broken data.

OPTIONS:
    $yellow-h, --help$white
        Print this help and exit.

    $yellow-v[v[v]]$white
        Log verbosity. Max 3 levels, eg -vvv

    $yellow-p, --perform$white
        If set, performs repair: otherwise only detects broken data.

    $yellow-f=FOLDERS, --folder=FOLDERS$white
        If provided, limit the detection to only courses imported into these folders.

        May be provided more than once, and/or as a comma-separated list of folders.


HELP;

        exit(CliKernelAbstract::EXIT_STATUS_OK);
    }
}
