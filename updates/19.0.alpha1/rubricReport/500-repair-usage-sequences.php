#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Alaress\Database;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Finds any submission boxes that have Capability or Indicator usages with sequence collisions, and repairs them.
 */
$app = new class extends CliTool\App
{

    /** @var int */
    private $countCapabilityUsagesChanged = 0;

    /** @var int */
    private $countIndicatorUsagesChanged = 0;


    /**
     * Hands off execution over to the App
     */
    public function run(): void
    {
        $this->getDatabaseInternal()->beginTransaction();
        try {
            $this->checkAndRepairUsages();
            $this->getDatabaseInternal()->commit();
        } catch (Database\Exception $e) {
            $this->getLogger()->error('Database error, rolling back transaction', ['exception' => $e]);
            $this->getLogger()->error($e->getTraceAsString());
            $this->getDatabaseInternal()->rollBack();
        }
    }


    /**
     * Checks and repairs usage sequences, then prints results
     *
     * @throws Database\Exception
     */
    private function checkAndRepairUsages(): void
    {
        $this->checkAndRepairCapabilityUsages();
        $this->checkAndRepairIndicatorUsages();
        $this->printResults();
    }


    /**
     * Checks and repairs Capability Usages sequences
     *
     * @throws Database\Exception
     */
    private function checkAndRepairCapabilityUsages(): void
    {
        $this->getLogger()->info('Finding DueWorks having Capability Usages with sequence collisions');
        $submissionBoxIds = $this->getCapabilitySequenceCollisions();

        if (!count($submissionBoxIds)) {
            $this->getLogger()->info('No Capability Usage sequence collisions found.');
        } else {
            $this->getLogger()->info('Repairing Capability Usage sequences');
            $this->repairCapabilityUsages($submissionBoxIds);
        }
    }


    /**
     * Checks and repairs Indicator Usage sequences
     */
    private function checkAndRepairIndicatorUsages(): void
    {
        $this->getLogger()->info('Finding DueWorks having Indicator Usages with sequence collisions');
        $submissionBoxAndRubricCapabilityIds = $this->getIndicatorSequenceCollisions();

        if (!count($submissionBoxAndRubricCapabilityIds)) {
            $this->getLogger()->info('No Indicator Usage sequence collisions found.');
        } else {
            $this->getLogger()->info('Repairing Indicator Usage sequences');
            $this->repairIndicatorUsages($submissionBoxAndRubricCapabilityIds);
        }
    }


    /**
     * Return the ids of submission boxes that have capability usages having sequence collisions
     *
     * @return array
     */
    private function getCapabilitySequenceCollisions(): array
    {
        // Fetch DueWork which use different capabilities, but have the same sequence
        $sql = "
            SELECT DISTINCT rgu1.submission_box_id
            FROM rubric_group_usage rgu1
            JOIN rubric_group_usage rgu2 ON 
                rgu1.rubric_group_id   != rgu2.rubric_group_id AND
                rgu1.submission_box_id =  rgu2.submission_box_id AND
                rgu1.sequence          =  rgu2.sequence;
        ";
        return $this->getDatabaseInternal()->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Return the ids of submission boxes and rubric capabilities that have indicator usages having sequence collisions
     *
     * @return array
     */
    private function getIndicatorSequenceCollisions(): array
    {
        // Fetch DueWork which use different indicators, that belong to the same capability, but the indicators have the same sequence.
        $sql = "
            SELECT DISTINCT rcu1.submission_box_id, rc1.rubric_group_id
            FROM rubric_criterion rc1
            JOIN rubric_criterion_usage rcu1 ON rcu1.criterion_id = rc1.id
            JOIN rubric_criterion rc2
            JOIN rubric_criterion_usage rcu2 ON rcu2.criterion_id = rc2.id
            WHERE
                rc1.rubric_group_id    =  rc2.rubric_group_id AND
                rcu1.submission_box_id =  rcu2.submission_box_id AND
                rcu1.criterion_id      != rcu2.criterion_id AND
                rcu1.sequence          =  rcu2.sequence;
        ";
        return $this->getDatabaseInternal()->executeQuery($sql)->fetchAll(PDO::FETCH_NUM);
    }


    /**
     * Repairs Capability Usage sequences for the specified submission boxes
     *
     * @param array $submissionBoxIds
     * @throws Database\Exception
     */
    private function repairCapabilityUsages(array $submissionBoxIds): void
    {
        $selectStmt = $this->getDatabaseInternal()->prepareQuery('
            SELECT rubric_group_id, sequence
            FROM rubric_group_usage 
            WHERE submission_box_id = :submission_box_id
            ORDER BY sequence ASC, rubric_group_id ASC
        ');

        $updateStmt = $this->getDatabaseInternal()->prepareQuery('
            UPDATE rubric_group_usage
            SET sequence = :sequence
            WHERE 
                rubric_group_id = :rubric_group_id AND 
                submission_box_id = :submission_box_id
        ');

        foreach ($submissionBoxIds as $submissionBoxId) {
            $this->getLogger()->info("Repairing Submission Box ($submissionBoxId)");
            $selectStmt->execute(['submission_box_id' => $submissionBoxId]);
            $capabilityUsages = $selectStmt->fetchAll();
            $this->getLogger()->info("    Old Capability Usage sequences: {$this->formatSequenceString($capabilityUsages, 'rubric_group_id')}");
            foreach ($capabilityUsages as $idx => $row) {
                $oldSequence = (int)$row['sequence'];
                $newSequence = $idx + 1;
                if ($oldSequence !== $newSequence) {
                    $updateStmt->execute([
                        'sequence'          => $newSequence,
                        'rubric_group_id'   => $row['rubric_group_id'],
                        'submission_box_id' => $submissionBoxId,
                    ]);
                    $this->getLogger()->debug("Capability {$row['rubric_group_id']} new sequence: $newSequence");
                    $this->countCapabilityUsagesChanged++;
                }
            }
        }
    }


    /**
     * Repairs Indicator Usage sequences for the specified submission boxes
     *
     * @param array $submissionBoxAndRubricCapabilityIds
     * @throws Database\Exception
     */
    private function repairIndicatorUsages(array $submissionBoxAndRubricCapabilityIds): void
    {
        $selectStmt = $this->getDatabaseInternal()->prepareQuery('
            SELECT criterion_id, sequence
            FROM rubric_criterion_usage rcu
            JOIN rubric_criterion rc ON rcu.criterion_id = rc.id
            WHERE submission_box_id = :submission_box_id AND rc.rubric_group_id = :rubric_group_id
            ORDER BY sequence ASC, criterion_id ASC
        ');

        $updateStmt = $this->getDatabaseInternal()->prepareQuery('
            UPDATE rubric_criterion_usage
            SET sequence = :sequence
            WHERE 
                criterion_id = :criterion_id AND 
                submission_box_id = :submission_box_id
        ');

        foreach ($submissionBoxAndRubricCapabilityIds as [$submissionBoxId, $rubricCapabilityId]) {
            $this->getLogger()->info("Repairing Submission Box ($submissionBoxId), Capability: ({$rubricCapabilityId})");
            $selectStmt->execute(['submission_box_id' => $submissionBoxId, 'rubric_group_id' => $rubricCapabilityId]);
            $indicatorUsages = $selectStmt->fetchAll();
            $this->getLogger()->info("    Old Indicator Usage sequences: {$this->formatSequenceString($indicatorUsages, 'criterion_id')}");
            foreach ($indicatorUsages as $idx => $row) {
                $oldSequence = (int)$row['sequence'];
                $newSequence = $idx + 1;
                if ($oldSequence !== $newSequence) {
                    $updateStmt->execute([
                        'sequence'          => $newSequence,
                        'criterion_id'      => $row['criterion_id'],
                        'submission_box_id' => $submissionBoxId,
                    ]);
                    $this->getLogger()->debug("Indicator {$row['criterion_id']} new sequence: $newSequence");
                    $this->countIndicatorUsagesChanged++;
                }
            }
        }
    }


    /**
     * Returns a set of Capability/Indicator usages as a human-friendly string
     *
     * @param array  $usages
     * @param string $entityIdField
     * @return string
     */
    private function formatSequenceString(array $usages, string $entityIdField): string
    {
        $mapped = array_map(static function (array $usage) use ($entityIdField) {
            return $usage[$entityIdField] . ' => ' . $usage['sequence'];
        }, $usages);
        return '[' . implode(', ', $mapped) . ']';
    }


    /**
     * Prints the results of the tool's execution
     */
    private function printResults(): void
    {
        $this->getLogger()->info("{$this->countCapabilityUsagesChanged} Capability Usage sequences updated");
        $this->getLogger()->info("{$this->countIndicatorUsagesChanged} Indicator Usage sequences updated");
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, null, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();




