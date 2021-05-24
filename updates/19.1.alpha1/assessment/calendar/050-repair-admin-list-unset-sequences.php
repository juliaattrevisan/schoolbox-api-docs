#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../../conf/core/require.php';

/**
 * Repairs unset sequence values across AdminLists lookup tables
 */
$app = new class extends CliTool\App
{
    /** Hands off execution over to the App */
    public function run(): void
    {
        $this->getDatabaseInternal()->beginTransaction();
        try {
            $this->repairNewsTopics();
            $this->repairPastoralSeverity();
            $this->getDatabaseInternal()->commit();
        } catch (Database\Exception $e) {
            $this->getLogger()->error('Database error, rolling back transaction', ['exception' => $e]);
            $this->getLogger()->error($e->getMessage());
            $this->getLogger()->error($e->getTraceAsString());
            $this->getDatabaseInternal()->rollBack();
        }
    }


    /**
     * Repair `newsTopic`.`sequence`
     */
    private function repairNewsTopics(): void
    {
        // Sequence starts from this.
        $sequenceBase = Schoolbox\Comms\News\Topic\Model::sequenceBase();

        $query = "
            SELECT newsTopicID, name, sequence 
            FROM newsTopic 
            WHERE sequence IS NULL OR sequence < :sequence_base
        ";
        $invalidNewsTopics = $this->getDatabaseInternal()->executeQuery($query, ['sequence_base' => $sequenceBase])->fetchAll();

        if (!count($invalidNewsTopics)) {
            $this->getLogger()->info("No invalid sequences found in table 'newsTopic'");
            return;
        }

        $this->getLogger()->warning(sprintf(
            "%d invalid sequences found in table 'newsTopic', re-sequencing from %d...",
            count($invalidNewsTopics),
            $sequenceBase
        ));

        $newsTopics = $this->getDatabaseInternal()->executeQuery(
            "SELECT newsTopicID, name, sequence FROM newsTopic"
        )->fetchAll();

        $updateStatement = $this->getDatabaseInternal()->prepareQuery("
            UPDATE newsTopic SET sequence = :sequence WHERE newsTopicID = :newsTopicID
        ");

        $countUpdates = 0;
        $expectedSequence = $sequenceBase - 1;
        foreach ($newsTopics as $newsTopic) {
            $expectedSequence++;
            $id              = $newsTopic['newsTopicID'];
            $name            = $newsTopic['name'];
            $currentSequence = $newsTopic['sequence'] ?? null;

            if ((int)$currentSequence === $expectedSequence) {
                $this->getLogger()->debug(
                    "News Topic '$name' (id: $id) sequence unchanged: $expectedSequence"
                );
                continue;
            }
            $updateStatement->execute(['sequence' => $expectedSequence, 'newsTopicID' => $id]);
            $countUpdates++;
            $this->getLogger()->debug(sprintf(
                "News Topic '%s' (id: %d) sequence changed from %s to %d",
                $name,
                $id,
                $currentSequence ?? "'null'",
                $expectedSequence
            ));
        }
        $this->getLogger()->info("$countUpdates NewsTopic sequences updated");
    }

    /**
     * Repair `lu_pastoral_severity`.`order`
     */
    private function repairPastoralSeverity(): void
    {
        // Sequence starts from this.
        $sequenceBase = Schoolbox\Pastoral\Severity\Model::sequenceBase();

        // Repair any soft-deleted
        $query = "
            UPDATE lu_pastoral_severity
            SET `order` = :sequence_base_1
            WHERE (`order` IS NULL OR `order` < :sequence_base_2) AND deleted_at IS NOT NULL
        ";
        $this->getDatabaseInternal()->executeQuery($query, ['sequence_base_1' => $sequenceBase, 'sequence_base_2' => $sequenceBase]);

        // Repair non-deleted
        $query = "
            SELECT id, name, `order`
            FROM lu_pastoral_severity
            WHERE (`order` IS NULL OR `order` < :sequence_base) AND deleted_at IS NULL
        ";
        $invalidSeverityRows = $this->getDatabaseInternal()->executeQuery($query, ['sequence_base' => $sequenceBase])->fetchAll();

        if (!count($invalidSeverityRows)) {
            $this->getLogger()->info("No invalid severity records found in table 'lu_pastoral_severity'");
            return;
        }

        $this->getLogger()->warning(sprintf(
            "%d invalid records found in table 'lu_pastoral_severity', re-sequencing from %d...",
            count($invalidSeverityRows),
            $sequenceBase
        ));

        $severityRows = $this->getDatabaseInternal()->executeQuery(
            "SELECT id, name, `order` FROM lu_pastoral_severity WHERE deleted_at IS NULL"
        )->fetchAll();

        $updateStatement = $this->getDatabaseInternal()->prepareQuery("
            UPDATE lu_pastoral_severity SET `order` = :order WHERE id = :id
        ");

        $countUpdates = 0;
        $expectedSequence = $sequenceBase - 1;
        foreach ($severityRows as $severityRow) {
            $expectedSequence++;
            $id              = $severityRow['id'];
            $name            = $severityRow['name'];
            $currentSequence = $severityRow['order'] ?? null;

            if ((int)$currentSequence === $expectedSequence) {
                $this->getLogger()->debug(
                    "Pastoral Severity '$name' (id: $id) sequence unchanged: $expectedSequence"
                );
                continue;
            }
            $updateStatement->execute(['order' => $expectedSequence, 'id' => $id]);
            $countUpdates++;
            $this->getLogger()->debug(sprintf(
                "Pastoral Severity '%s' (id: %d) sequence changed from %s to %d",
                $name,
                $id,
                $currentSequence ?? "'null'",
                $expectedSequence
            ));
        }
        $this->getLogger()->info("$countUpdates Pastoral Severity sequences updated");
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
