<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair\Unused\Delete;

use Alaress\Database\Statement\IStatement;
use Alaress_Date_Format;
use Schoolbox\Application\CliTool;
use Throwable;

class Indicator extends CliTool\App
{
    private const OPT_LONG_FOLDER_IDS = 'folders:';

    private const INDENT = '  ';

    /**
     * How many characters max of a capability or indicator name are in the log?
     */
    private const MAX_CHARS_IN_OUTPUT_NAME = 40;

    /**
     * Query for finding a folder's name and class codes.
     */
    private const FIND_FOLDER_NAME_QUERY = "
        SELECT f.name,
               GROUP_CONCAT(fc.code ORDER BY fc.code SEPARATOR ', ') AS codes
        FROM      folder f
        LEFT JOIN folder_code fc ON f.id = fc.folder_id
        WHERE f.id = :folderId
        AND   f.deleted_at  IS NULL
        AND   fc.deleted_at IS NULL
        GROUP BY fc.code
        ORDER BY fc.code
    ";

    /**
     * Query for finding if the requested folder is a course spec.
     *
     * Indicators will not be removed from course specs: it's too risky, because
     * this course's indicators could be used in unit spec assessments.
     */
    private const FIND_COURSE_SPEC_QUERY = "SELECT * FROM course_specification WHERE folder_id = :folderId";

    /**
     * Query for finding rubric capabilities within a folder.
     */
    private const FIND_RUBRIC_CAPABILITY_QUERY = "
        SELECT id, name
        FROM   rubric_capability
        WHERE  parent_type = 'category'
        AND    parent_id   = :folderId
        AND    deleted_at IS NULL
        ORDER BY id
    ";

    /**
     * Query for finding rubric indicators within a capability.
     */
    private const FIND_RUBRIC_INDICATOR_QUERY = "
        SELECT id, name
        FROM   rubric_indicator
        WHERE  rubric_capability_id = :capabilityId
        AND    deleted_at IS NULL
        ORDER BY id
    ";

    /**
     * Query for finding the number of usages of an indicator.
     */
    private const FIND_INDICATOR_USAGE_COUNT_QUERY = "
        SELECT
            COUNT(a.id) AS usages,
            vr.capability_id,
            vr.capability_name,
            vr.indicator_id,
            vr.indicator_name
        FROM       v_assessment_relation var
        INNER JOIN v_rubric              vr ON var.assessment_id = vr.assessment_id
        INNER JOIN assessment            a  ON var.assessment_id = a.id
        WHERE vr.indicator_id = :indicatorId
        AND   a.deleted_at IS NULL
        GROUP BY vr.capability_id, vr.capability_name, vr.indicator_id, vr.indicator_name
        ORDER BY vr.capability_id, vr.indicator_id
    ";

    /**
     * Query for deleting indicators
     */
    private const DELETE_RUBRIC_INDICATOR_QUERY = '
        UPDATE rubric_indicator
        SET    deleted_at = :now
        WHERE  id         = :indicatorId
    ';

    /**
     * Query for deleting capabilities
     */
    private const DELETE_RUBRIC_CAPABILITY_QUERY = '
        UPDATE rubric_capability
        SET    deleted_at = :now
        WHERE  id         = :capabilityId
    ';

    /**
     * Query for finding a folder's name.
     */
    private IStatement $findFolderNameQuery;

    /**
     * Query for finding if a folder is a course spec.
     */
    private IStatement $findCourseSpecQuery;

    /**
     * Query for finding rubric capabilities in a folder.
     */
    private IStatement $findRubricCapabilityQuery;

    /**
     * Query for finding rubric indicators in a capability.
     */
    private IStatement $findRubricIndicatorQuery;

    /**
     * Query for finding the number of usages of an indicator.
     */
    private IStatement $findIndicatorUsageCountQuery;

    /**
     * Query for deleting a rubric indicator from a unit folder.
     */
    private IStatement $deleteRubricIndicatorQuery;

    /**
     * Query for deleting a rubric capability from a folder.
     */
    private IStatement $deleteRubricCapabilityQuery;

    /**
     * The time at the start of the script run: stored so that any indicators
     * deleted are marked as deleted at a consistent time.
     */
    private string $now;

    /**
     * Array of one or more folder IDs to operate on.
     *
     * @var int[]
     */
    private array $folderIds;

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return array_merge(
            parent::getLongOpts(),
            [self::OPT_LONG_FOLDER_IDS,]
        );
    }


    /**
     * Set up and run the repair process.
     *
     * Install all instance vars, and wraps the repair process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @inheritdoc
     */
    public function run(): void
    {
        $db        = $this->getDatabaseInternal();
        $this->now = Alaress_Date_Format::now()->getDatabaseDate();

        // Set up folders
        $folderIds = $this->getOpt(self::OPT_LONG_FOLDER_IDS);
        if (!isset($folderIds)) {
            $this->getLogger()->error('Exiting - at least 1 folder ID must be supplied. Run again with --folders <id1,id2>');
            return;
        }
        $this->folderIds = array_map('intval', explode(",", $folderIds));

        // log perform / dry run
        if ($this->perform()) {
            $this->getLogger()->warning('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->getLogger()->warning('Running in dry-run mode: use -p to write changes to database');
        }

        // prepare queries
        $this->findFolderNameQuery          = $db->prepareQuery(self::FIND_FOLDER_NAME_QUERY);
        $this->findCourseSpecQuery          = $db->prepareQuery(self::FIND_COURSE_SPEC_QUERY);
        $this->findRubricCapabilityQuery    = $db->prepareQuery(self::FIND_RUBRIC_CAPABILITY_QUERY);
        $this->findRubricIndicatorQuery     = $db->prepareQuery(self::FIND_RUBRIC_INDICATOR_QUERY);
        $this->findIndicatorUsageCountQuery = $db->prepareQuery(self::FIND_INDICATOR_USAGE_COUNT_QUERY);
        $this->deleteRubricIndicatorQuery   = $db->prepareQuery(self::DELETE_RUBRIC_INDICATOR_QUERY);
        $this->deleteRubricCapabilityQuery  = $db->prepareQuery(self::DELETE_RUBRIC_CAPABILITY_QUERY);

        // Wrap entire loop in a transaction
        $db->beginTransaction();
        try {
            $this->repair();
            if ($this->perform()) {
                $this->getLogger()->notice('Repair complete: committing changes');
                $db->commit();
            } else {
                $this->getLogger()->notice('Repair dry run complete: rolling back changes');
                $db->rollBack();
            }
        } catch (Throwable $e) {
            $this->getLogger()->error('Error occurred during repair: rolling back changes');
            $db->rollBack();
            throw $e;
        }
    }


    /**
     * Run the repair process.
     */
    private function repair(): void
    {
        foreach ($this->folderIds as $folderId) {
            $this->findFolderNameQuery->execute(['folderId' => $folderId]);
            if ($this->findFolderNameQuery->rowCount() === 0) {
                // No folder found:
                $this->getLogger()->notice(sprintf('Folder #%d does not exist: skipping', $folderId));
                continue;
            }

            $this->findCourseSpecQuery->execute(['folderId' => $folderId]);
            if ($this->findCourseSpecQuery->rowCount() > 0) {
                // This is a course spec:
                $this->getLogger()->notice(sprintf('Folder #%d is a course specification: skipping', $folderId));
                continue;
            }

            $folder = $this->findFolderNameQuery->fetch();
            $this->getLogger()->info(
                sprintf(
                    'Deleting unused indicators in folder #%d (%s)',
                    $folderId,
                    $folder['name'] . (isset($folder['codes'])
                        ? ' (' . $folder['codes'] . ')'
                        : ''
                    )
                )
            );

            // Don't go further if there's no capabilities in folder
            $this->findRubricCapabilityQuery->execute(['folderId' => $folderId]);
            if ($this->findRubricCapabilityQuery->rowCount() === 0) {
                $this->getLogger()->debug(self::indent(1) . 'folder contains no capabilities: no action required');
                continue;
            }

            // This folder contains capabilities: great!
            // Let's find indicators, and then determine if they are used or not.
            $this->getLogger()->info(self::indent(1) . 'finding unused indicators...');

            // Iterate each indicator, delete ones which are empty
            $capabilities = $this->findRubricCapabilityQuery->fetchAll();
            foreach ($capabilities as $capability) {
                $this->findRubricIndicatorQuery->execute(['capabilityId' => $capability['id']]);
                foreach ($this->findRubricIndicatorQuery->fetchAll() as $indicator) {
                    $this->deleteIndicatorIfEmpty($indicator, $capability);
                }
            }

            // Clean up empty capabilities
            $this->getLogger()->info(self::indent(1) . 'cleaning up any empty capabilities left behind...');
            foreach ($capabilities as $capability) {
                $this->deleteCapabilityIfEmpty($capability);
            }

            // Should not need to clean up capabilities with unused indicators:
            // all these would have been cleaned up in the last step
//            $this->getLogger()->debug(self::indent(1) . 'cleaning up any capabilities with no indicators in use...');
//            $this->deleteCapabilitiesWithNoUsedIndicators($capabilities);
        }
    }

    /**
     * Delete the given indicator, if it is empty.
     *
     * @param array $indicator  The indicator to delete: contains the "id" and
     *                          "name" fields
     * @param array $capability The capability the indicator is in: contains the
     *                          "id" and "name" fields
     */
    private function deleteIndicatorIfEmpty(array $indicator, array $capability): void
    {
        // How many usages of this indicator are there?
        $this->findIndicatorUsageCountQuery->execute(['indicatorId' => $indicator['id']]);
        $indicatorUsageCount = (int)$this->findIndicatorUsageCountQuery->fetchColumn();

        $this->getLogger()->debug(
            self::indent(2)
            . sprintf(
                'indicator #%d (%s / %s) has %d usage(s)',
                $indicator['id'],
                self::formatNameForOutput($capability['name']),
                self::formatNameForOutput($indicator['name']),
                $indicatorUsageCount,
            )
        );

        // If it's empty, delete it
        if ($indicatorUsageCount === 0) {
            $this->getLogger()->info(
                self::indent(2)
                . sprintf(
                    'deleting unused indicator #%d (%s / %s)',
                    $indicator['id'],
                    self::formatNameForOutput($capability['name']),
                    self::formatNameForOutput($indicator['name']),
                )
            );

            $this->deleteRubricIndicatorQuery->execute(
                [
                    'now'         => $this->now,
                    'indicatorId' => $indicator['id'],
                ]
            );
        }
    }

    /**
     * Delete the given capability, if it is empty.
     *
     * @param array $capability The capability the indicator is in: contains the
     *                          "id" and "name" fields
     */
    private function deleteCapabilityIfEmpty(array $capability): void
    {
        // Are there any extant indicators in this capability?
        // If so, do not delete this capability.
        $this->findRubricIndicatorQuery->execute(['capabilityId' => $capability['id']]);
        if ($this->findRubricIndicatorQuery->rowCount() === 0) {
            $this->getLogger()->info(
                sprintf(
                    self::indent(2) . 'deleting empty capability #%d (%s)',
                    $capability['id'],
                    self::formatNameForOutput($capability['name']),
                )
            );
            $this->deleteRubricCapabilityQuery->execute(
                [
                    'now'          => $this->now,
                    'capabilityId' => $capability['id'],
                ]
            );
        }
    }

    /**
     * Helper method for indenting log output for legibility.
     *
     * @param int $depth The depth to indent by
     * @return string
     */
    private static function indent(int $depth): string
    {
        return str_repeat(self::INDENT, $depth);
    }

    /**
     * Truncate and normalize space in `$name` for output in a log message.
     *
     * @param string $name
     * @return string
     */
    private static function formatNameForOutput(string $name): string
    {
        $name = trim(preg_replace('/\\s+/', ' ', $name));
        return mb_strlen($name) > self::MAX_CHARS_IN_OUTPUT_NAME
            ? mb_substr($name, 0, self::MAX_CHARS_IN_OUTPUT_NAME) . '...'
            : $name;
    }
}
