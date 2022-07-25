<?php

/**
 * Rubrics Mapper
 *
 * @author    Tom McDonnell <tom.mcdonnell@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Rubrics\ResultSets;

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
     * @return array
     */
    public function findSets(): array
    {
        $pdoStatement = $this->database->prepare('SELECT * FROM rubric_result');
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
     * @param array $items
     * @return void
     * @throws Exception
     */
    public function deleteRubricResultSet(int $resultSetId): void
    {
        $stmt = $this->database->prepare(
            "DELETE FROM rubric_result
            WHERE resultset_id = :resultSetId"
        );

        $stmt->execute(array('resultSetId' => $resultSetId));
    }
}
