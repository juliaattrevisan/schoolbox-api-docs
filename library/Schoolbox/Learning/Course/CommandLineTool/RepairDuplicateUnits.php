<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Course\CommandLineTool;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use UnexpectedValueException;

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

    /**
     * Query for finding the IDs of duplicate unit specs in a single
     * implementation.
     */
    private const SELECT_DUPLICATE_UNITS_QUERY = <<<SQL
        SELECT
            cui.course_implementation_id,
            cui.course_unit_specification_id,
            cui.title AS unit_title,
            ci.folder_id,
            f.name AS folder_title,
            count(*) AS c
        FROM course_unit_implementation cui
        INNER JOIN course_implementation ci ON cui.course_implementation_id = ci.id
        INNER JOIN folder f ON f.id = ci.folder_id
        WHERE %s
        GROUP BY
            cui.course_implementation_id,
            cui.course_unit_specification_id,
            ci.folder_id
        HAVING c > 1
        ORDER BY ci.folder_id, cui.course_unit_specification_id
    SQL;

    /**
     * Query for finding duplicate unit IDs.
     */
    private const SELECT_DUPLICATE_UNITS_IN_COURSE_QUERY = <<<SQL
        SELECT
            id,
            open_date,
            due_date,
            created_at
        FROM course_unit_implementation
        WHERE course_implementation_id = :courseImplementationId
        AND course_unit_specification_id = :courseUnitSpecificationId
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
        $log = $this->getLogger();
        if ($this->perform() && !$this->isInteractive()) {
            $log->error('This tool requires an interactive session if performing changes.');
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            $this->printHelp();
        }

        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        $unitImplMapper       = UnitImplementationMapper::getInstance();

        // Build the find units query
        $db = $this->getDatabaseInternal();
        $pb = $db->buildPredicateBuilder('cui')
                 ->isNull('cui.deleted_at');
        $folders = array_merge(
            $this->extractFolderIdOpts($this->getOpt(self::OPT_LONG_FOLDER)),
            $this->extractFolderIdOpts($this->getOpt(self::OPT_SHORT_FOLDER)),
        );
        if (!empty($folders)) {
            $log->notice('Searching folders: ' . implode(', ', $folders));
            $pb = $pb->in('ci.folder_id', $folders);
        }

        $duplicateUnitsInCourseQuery = $db->prepare(self::SELECT_DUPLICATE_UNITS_IN_COURSE_QUERY);
        $duplicateUnitsQuery = $db->prepare(sprintf(self::SELECT_DUPLICATE_UNITS_QUERY, $pb->getWhereIntersect()));
        $duplicateUnitsQuery->execute($pb->getParams());
        $log->notice(sprintf('%s duplicate units found.', $duplicateUnitsQuery->rowCount()));
        foreach ($duplicateUnitsQuery as $duplicateUnitsRow) {
            // Log the unit spec which is duplicated within each course impl
            $log->notice(
                sprintf(
                    'Folder #%d (%s), course impl #%d, unit spec #%d (%s): %d duplicates',
                    $duplicateUnitsRow['folder_id'],
                    $duplicateUnitsRow['folder_title'],
                    $duplicateUnitsRow['course_implementation_id'],
                    $duplicateUnitsRow['course_unit_specification_id'],
                    $duplicateUnitsRow['unit_title'],
                    $duplicateUnitsRow['c'],
                ),
            );

            // Now what to do with them?
            $duplicateUnitsInCourseQuery->execute([
                'courseImplementationId'    => $duplicateUnitsRow['course_implementation_id'],
                'courseUnitSpecificationId' => $duplicateUnitsRow['course_unit_specification_id'],
            ]);
            $unitImplIds = [];
            foreach ($duplicateUnitsInCourseQuery as $duplicateUnitsInCourseQueryRow) {
                $unitImplIds[] = $duplicateUnitsInCourseQueryRow['id'];
                $log->info(
                    sprintf(
                        '- unit impl #%d, open %s, due %s',
                        $duplicateUnitsInCourseQueryRow['id'],
                        $duplicateUnitsInCourseQueryRow['open_date'],
                        $duplicateUnitsInCourseQueryRow['due_date'],
                    ),
                );
                /** @var IAssessable[] $assessment */
                $assessments = $assessmentDataSource->findAllByUnitImplementationIds([$duplicateUnitsInCourseQueryRow['id']]);
                $assessments = $assessmentDataSource->loadParticipantsIntoAssessmentCollection($assessments);
                foreach ($assessments as $assessment) {
                    $log->info(
                        sprintf(
                            '  - assessment #%d (%s), open %s, due %s, %d participants completed',
                            $assessment->getId(),
                            $assessment->getTitle(),
                            $assessment->getOpenAt()->getDatabase(),
                            $assessment->getDueAt()->getDatabase(),
                            count($assessment->getAllParticipants()->filter(fn (Participant $p) => $p->hasPublishedFeedback())),
                        ),
                    );
                }
            }

            // Now ask which to preserve
            if ($this->perform()) {
                $log->info('---');
                if (count($unitImplIds) <= 1) {
                    $log->warning(sprintf('No duplicate units found in course'));
                } else {
                    $preservedUnitImplId = $this->readPreservedUnitImplId($unitImplIds);
                    if (isset($preservedUnitImplId)) {
                        $deletedUnitImplIds = array_diff($unitImplIds, [$preservedUnitImplId]);
                        $log->notice(sprintf('Preserving unit impl: %s', $preservedUnitImplId));
                        $log->notice(sprintf('Deleting unit impls: %s', implode(',', $deletedUnitImplIds)));
                        $unitImplMapper->delete($unitImplMapper->findByColumn('id', $deletedUnitImplIds));
                    } else {
                        $log->notice('Skipping unit impl delete');
                    }
                }
                $log->info('---');
            }
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

        This tool repairs broken data by asking which unit implementation to preserve: all other duplicates will be
        deleted. Therefore, this flag may not be provided if this tool is running in a non-interactive session.

    $yellow-f=FOLDERS, --folder=FOLDERS$white
        If provided, limit the detection to only courses imported into these folders.

        May be provided more than once, and/or as a comma-separated list of folders.


HELP;

        exit(CliKernelAbstract::EXIT_STATUS_OK);
    }

    /**
     * Extract folder IDs.
     *
     * @param mixed $folderIdOpts
     * @return array<positive-int>
     * @throws UnexpectedValueException if any folder ID is not a positive int
     */
    private function extractFolderIdOpts(mixed $folderIdOpts): array
    {
        // No folder options passed:
        // return empty
        if (empty($folderIdOpts)) {
            return [];
        }

        // More than one folder option passed:
        // extract IDs from all of them recursively
        if (is_array($folderIdOpts)) {
            return array_merge(...array_map($this->extractFolderIdOpts(...), $folderIdOpts));
        }

        // One folder option passed:
        // Split into IDs by comma, raising error if any is not a positive int
        $folderIds = [];
        foreach (explode(',', $folderIdOpts) as $folderId) {
            $folderIdInt = filter_var($folderId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            if (!isset($folderIdInt) || $folderIdInt <= 0) {
                throw new UnexpectedValueException(
                    sprintf('Folder ID value "%s" is not a positive integer', $folderId)
                );
            }
            $folderIds[] = (int)$folderIdInt;
        }
        return $folderIds;
    }

    /**
     * Read one of the values from the command line, out of `$unitImplIds`.
     *
     * Keep attempting to read until one of `$unitImplIds` is read, or an empty
     * string is read: in this case skip.
     *
     * @param array<int> $unitImplIds
     * @return int|null
     */
    private function readPreservedUnitImplId(array $unitImplIds): ?int
    {
        $unitImplId = null;
        while (!isset($unitImplId)) {
            $unitImplId = $this->readLine(
                sprintf('Enter unit impl ID to preserve (%s), or ENTER to skip: ', implode(', ', $unitImplIds))
            );
            if ($unitImplId === '') {
                return null;
            }
            if (!in_array($unitImplId, $unitImplIds)) {
                $unitImplId = null;
            }
        }
        return (int)$unitImplId;
    }
}
