<?php

/**
 * Rubrics Mapper
 *
 * @author    Tom McDonnell <tom.mcdonnell@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Rubrics\ResultSet;

use Exception;
use http\Exception\InvalidArgumentException;
use PDO;
use Schoolbox\Assessment\Rubric\DataModel\Result\Model;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        // The rubric_result table has a compound primary key (resultset_id, value), so the base mapper functions are
        // not very useful, and so this function has been deliberately left empty.
    }

    /**
     * This function gets the data directly from the database instead of using mappers and models.
     * We do this because the base mapper functions do not work with tables with compound primary keys.
     * (Table result_rubric has a compound primary key on (resultset_id, value)).
     * @param int $resultSetId
     * @return array
     */
    public function findRubricResultRows(int $resultSetId): array
    {
        $stmt = $this->database->prepare('SELECT * FROM rubric_result WHERE resultset_id = :resultSetId');
        $stmt->execute(array('resultSetId' => $resultSetId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param int $resultSetId (Supply -1 to create a new rubric result set.  MAX(resultset_id)+1 will be used.)
     * @param array $labelsInOrder
     * @return int ($resultSetId)
     * @throws \InvalidArgumentException
     */
    public function createOrOverwriteRubricResultSet(int $resultSetId, array $labelsInOrder): int
    {
        if ($resultSetId === -1) {
            $stmt = $this->database->prepare(
                'SELECT resultset_id
                FROM rubric_result
                ORDER BY resultset_id DESC
                LIMIT 1'
            );
            $stmt->execute();

            $rows        = $stmt->fetchAll();
            $resultSetId = (count($rows) == 0) ? 1 : (int)$rows[0]['resultset_id'];
        }

        $stmt = $this->database->prepare(
            "DELETE FROM rubric_result
            WHERE resultset_id = :resultSetId"
        );
        $stmt->execute(array('resultSetId' => $resultSetId));

        $counter       = 0;
        $sqlInsertStmt = $this->database->prepare(
            "INSERT INTO rubric_result SET
            resultset_id = :resultSetId,
            value = :value,
            label = :label"
        );

        foreach ($labelsInOrder as $label) {
            // Avoid saving blank values as that will be confusing for everyone.
            if ('' === trim((string)$label)) {
                throw new \InvalidArgumentException("Expected only non-blank labels.  Blank label found.");
            }

            /* Forcibly truncate $label values, so they don't exceed Model::LABEL_MAXLENGTH chars.
             * This is the maximum width allowed in our db schema and will cause sentry errors
             * unless we forcibly ensure that it doesn't exceed the space available
             */
            $truncatedLabel = mb_substr($label, 0, Model::LABEL_MAXLENGTH, 'UTF-8');

            $sqlInsertStmt->execute(
                array(
                    'resultSetId' => $resultSetId,
                    'value'       => $counter++,
                    'label'       => $truncatedLabel
                )
            );
        }

        return $resultSetId;
    }
}
