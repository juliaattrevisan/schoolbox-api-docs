#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityUsage\Model as CapabilityUsageModel;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorUsage\Model as IndicatorUsageModel;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Finds any submission boxes that have Capability or Indicator usages with sequence collisions, and repairs them.
 * Invalid if:
 * * sequence set contains a value that is 0
 * * sequence set is not distinct
 */
$app = new class extends CliTool\App
{

    /** @var int */
    private $countCapabilityUsagesChanged = 0;

    /** @var int */
    private $countIndicatorUsagesChanged = 0;


    /**
     * Hands off execution over to the App
     *
     * @throws PDOException
     */
    public function run(): void
    {
        $this->getDatabaseInternal()->beginTransaction();
        try {
            $this->executeCheckAndRepair();
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
    private function executeCheckAndRepair(): void
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
        $submissionBoxIdsWithCollisions = $this->getCapabilitySequenceCollisions();
        $this->getLogger()->info(sprintf('%d Capability Usage sequence collisions found.', count($submissionBoxIdsWithCollisions)));

        $this->getLogger()->info('Finding DueWorks having Capability Usages with a 0 sequence');
        $submissionBoxIdsWithInvalid = $this->findCapabilityUsagesWithInvalidSequence();
        $this->getLogger()->info(sprintf('%d Submission boxes found with invalid Capability sequences.', count($submissionBoxIdsWithInvalid)));

        $submissionBoxIds = array_unique(array_merge($submissionBoxIdsWithCollisions, $submissionBoxIdsWithInvalid));
        $this->repairCapabilityUsages($submissionBoxIds);
    }


    /**
     * Checks and repairs Indicator Usage sequences
     */
    private function checkAndRepairIndicatorUsages(): void
    {
        $this->getLogger()->info('Finding DueWorks having Indicator Usages with sequence collisions');
        $submissionBoxAndCapabilitiesWithCollisions = $this->getIndicatorSequenceCollisions();
        $this->getLogger()->info(sprintf('%d Indicator Usage sequence sets with collisions found.', count($submissionBoxAndCapabilitiesWithCollisions)));

        $this->getLogger()->info('Finding DueWorks having Indicator Usages with a 0 sequence');
        $submissionBoxIdsWithInvalid = $this->findIndicatorUsagesWithInvalidSequence();
        $this->getLogger()->info(sprintf('%d Submission box / Capability found with invalid Indicator sequences.', count($submissionBoxIdsWithInvalid)));


        // Combine and get distinct submissionBox/Capability
        $unique = [];
        foreach ([$submissionBoxAndCapabilitiesWithCollisions, $submissionBoxIdsWithInvalid] as $submissionBoxAndCapabilityId) {
            foreach ($submissionBoxAndCapabilityId as [$submissionBoxId, $rubricCapabilityId]) {
                if (!array_key_exists($submissionBoxId, $unique)) {
                    $unique[$submissionBoxId] = [];
                }
                $unique[$submissionBoxId][$rubricCapabilityId] = (int)$rubricCapabilityId;
            }
        }

        // Flatten
        $flat = [];
        foreach ($unique as $submissionBoxId => $rubricCapabilityIds) {
            foreach ($rubricCapabilityIds as $rubricCapabilityId) {
                $flat[] = [$submissionBoxId, $rubricCapabilityId];
            }
        }

        $this->repairIndicatorUsages($flat);
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
     * Return the ids of submission boxes that have capability usages with invalid sequences.
     *
     * An invalid sequence is 0 (column type is unsigned, so negative numbers are impossible)
     *
     * @return array
     */
    private function findCapabilityUsagesWithInvalidSequence(): array
    {
        $sql = "
            SELECT DISTINCT submission_box_id
            FROM rubric_group_usage
            WHERE sequence = 0
            ORDER BY submission_box_id
        ";
        return $this->getDatabaseInternal()->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Return the ids of submission boxes that have indicator usages with invalid sequences.
     *
     * An invalid sequence is 0 (column type is unsigned, so negative numbers are impossible)
     *
     * @return array
     */
    private function findIndicatorUsagesWithInvalidSequence(): array
    {
        $sql = "
            SELECT DISTINCT rcu.submission_box_id, rc.rubric_group_id
            FROM rubric_criterion_usage rcu
            JOIN rubric_criterion rc ON rcu.criterion_id = rc.id
            WHERE sequence = 0
            ORDER BY submission_box_id, rubric_group_id
        ";
        return $this->getDatabaseInternal()->executeQuery($sql)->fetchAll(PDO::FETCH_NUM);
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
                rcu1.sequence          =  rcu2.sequence
            ORDER BY submission_box_id, rubric_group_id;
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
        if (!count($submissionBoxIds)) {
            $this->getLogger()->info('No Capability Usage sequences require repair');
            return;
        }
        $this->getLogger()->info(sprintf('Repairing %d distinct Capability Usage sequence sets', count($submissionBoxIds)));

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
            $newSequences     = [];
            $this->getLogger()->debug($this->formatSequenceString('Old Capability Usage sequences', $capabilityUsages, 'rubric_group_id'));
            $newSequence = CapabilityUsageModel::SEQUENCE_BASE;
            foreach ($capabilityUsages as $row) {
                $oldSequence = (int)$row['sequence'];
                if ($oldSequence !== $newSequence) {
                    $updateStmt->execute([
                        'sequence'          => $newSequence,
                        'rubric_group_id'   => $row['rubric_group_id'],
                        'submission_box_id' => $submissionBoxId,
                    ]);
                    $newSequences[] = ['rubric_group_id' => $row['rubric_group_id'], 'sequence' => $newSequence,];
                    $this->countCapabilityUsagesChanged++;
                    $newSequence++;
                }
            }
            $this->getLogger()->debug($this->formatSequenceString('New Capability Usage sequences', $newSequences, 'rubric_group_id'));
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
        if (!count($submissionBoxAndRubricCapabilityIds)) {
            $this->getLogger()->info('No SubmissionBox / Capabilities found with invalid Indicator Usage sequences');
        } else {
            $this->getLogger()->info(sprintf('Repairing %d distinct Indicator Usage sequence sets', count($submissionBoxAndRubricCapabilityIds)));
        }

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
            $newSequences    = [];
            $this->getLogger()->debug($this->formatSequenceString('Old Indicator Usage sequences', $indicatorUsages, 'criterion_id'));
            $newSequence = IndicatorUsageModel::SEQUENCE_BASE;
            foreach ($indicatorUsages as $idx => $row) {
                $oldSequence = (int)$row['sequence'];
                if ($oldSequence !== $newSequence) {
                    $updateStmt->execute([
                        'sequence'          => $newSequence,
                        'criterion_id'      => $row['criterion_id'],
                        'submission_box_id' => $submissionBoxId,
                    ]);
                    $newSequences[] = ['criterion_id' => $row['criterion_id'], 'sequence' => $newSequence,];
                    $this->countIndicatorUsagesChanged++;
                    $newSequence++;
                }
            }
            $this->getLogger()->debug($this->formatSequenceString('New Indicator Usage sequences', $newSequences, 'criterion_id'));
        }
    }


    /**
     * Returns a set of Capability/Indicator usages as a human-friendly string
     *
     * @param string $prefix
     * @param array  $usages
     * @param string $entityIdField
     * @return string
     */
    private function formatSequenceString(string $prefix, array $usages, string $entityIdField): string
    {
        $mapped = array_map(static function (array $usage) use ($entityIdField) {
            return $usage[$entityIdField] . ' => ' . $usage['sequence'];
        }, $usages);
        return "    $prefix  (id => seq.): [" . implode(', ', $mapped) . ']';
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
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
