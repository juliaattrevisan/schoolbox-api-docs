<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\RubricResultSet;

use PDO;
use Schoolbox\Assessment\Rubric\DataModel\Result\Model;
use Schoolbox\Mapper as BaseMapper;

/**
 * RubricResultSet mapper class.
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        // The rubric_result table has a compound primary key (resultset_id, value), so the base mapper functions are
        // not very useful.  We can still use the table name set here in SQL queries however.
        $this->hasTable('rubric_result');
    }

    /**
     * This function gets the data directly from the database instead of using mappers and models.
     * We do this because the base mapper functions do not work with tables with compound primary keys.
     * (Table result_rubric has a compound primary key on (resultset_id, value)).
     * @return array
     */
    public function findSets(): array
    {
        $pdoStatement = $this->database->prepare("SELECT * FROM {$this->getTable()}");
        $pdoStatement->execute();

        $rows = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);

        // Reformat data into arrays of rubrics indexed by resultSetId.
        $rubricsByResultSetId = [];
        foreach ($rows as $row) {
            $resultSetId = $row['resultset_id'];

            if (!array_key_exists($resultSetId, $rubricsByResultSetId)) {
                $rubricsByResultSetId[$resultSetId] = [];
            }

            $rubricsByResultSetId[$resultSetId][] = $row;
        }

        return $rubricsByResultSetId;
    }

    /**
     * @param int $resultSetId
     * @return void
     */
    public function deleteSet(int $resultSetId): void
    {
        $stmt = $this->database->prepare(
            "DELETE FROM {$this->getTable()}
            WHERE resultset_id = :resultSetId"
        );

        $stmt->execute(['resultSetId' => $resultSetId]);
    }

    /**
     * This function gets the data directly from the database instead of using mappers and models.
     * We do this because the base mapper functions do not work with tables with compound primary keys.
     * (Table result_rubric has a compound primary key on (resultset_id, value)).
     * @param int $resultSetId
     * @return array
     */
    public function findRows(int $resultSetId): array
    {
        $stmt = $this->database->prepare("SELECT * FROM {$this->getTable()} WHERE resultset_id = :resultSetId");
        $stmt->execute(['resultSetId' => $resultSetId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param int|null $resultSetId
     *    (Supply null to create a new rubric result set.  COALESCE(MAX(resultset_id)+1, 1) will be used for the new resultSetId.)
     * @param array $labelsInOrder
     * @return int ($resultSetId)
     */
    public function createOrOverwriteSet(?int $resultSetId, array $labelsInOrder): int
    {
        if ($resultSetId === null) {
            // We are creating a new result set, so we must choose a resultSetId.
            $stmt = $this->database->prepare(
                "SELECT COALESCE(MAX(resultset_id)+1, 1) AS resultSetId
                FROM {$this->getTable()}"
            );
            $stmt->execute();

            $resultSetId = $stmt->fetchColumn();
        }

        $stmt = $this->database->prepare(
            "DELETE FROM {$this->getTable()}
            WHERE resultset_id = :resultSetId"
        );
        $stmt->execute(['resultSetId' => $resultSetId]);

        $counter       = 0;
        $sqlInsertStmt = $this->database->prepare(
            "INSERT INTO {$this->getTable()} SET
            resultset_id = :resultSetId,
            value = :value,
            label = :label"
        );

        foreach ($labelsInOrder as $label) {
            // Avoid saving blank values as that will be confusing for everyone.
            if ('' === trim((string)$label)) {
                // If we encounter a blank label, it is best to skip it rather than throwing an exception.  The client
                // does its best to prevent blank labels from being submitted, but client-side validation code can
                // be bypassed by a determined user.  So that this will happen is expected and not exceptional.
                continue;
            }

            // Truncate label to the maximum allowed number of characters.
            // Note the limit is applied as a maxlength restriction on the input too, so no data will be lost in normal circumstances.
            // Trim here also to prevent whitespace-surrounded data from entering the database.
            $truncatedLabel = mb_substr(trim((string)$label), 0, Controller::MAX_CHARS_PER_LABEL, 'UTF-8');

            $sqlInsertStmt->execute(
                [
                    'resultSetId' => $resultSetId,
                    'value'       => $counter++,
                    'label'       => $truncatedLabel
                ]
            );
        }

        return $resultSetId;
    }
}
