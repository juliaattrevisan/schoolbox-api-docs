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
    public function saveRubricResults(array $items): void
    {
        $stmt = $this->database->prepare(
            "INSERT INTO rubric_result
            VALUES (:resultset_id, :value, :label)
            ON DUPLICATE KEY UPDATE label = :label"
        );

        foreach ($items as $setId => $results) {
            if (!(is_int($setId) && is_array($results) && count($results) > 0)) {
                continue;
            }

            // Avoid saving blank values as that will be confusing for everyone.
            foreach ($results as $lbl) {
                if ('' === trim((string)$lbl)) {
                    throw new InvalidArgumentException("Expected only non-blank labels.  Blank label found.");
                }
            }

            foreach ($results as $value => $label) {
                /* Forcibly truncate $label values, so they don't exceed Model::LABEL_MAXLENGTH chars.
                 * This is the maximum width allowed in our db schema and will cause sentry errors
                 * unless we forcibly ensure that it doesn't exceed the space available
                 */
                $truncatedLabel = mb_substr($label, 0, Model::LABEL_MAXLENGTH, 'UTF-8');

                $stmt->execute(
                    array(
                       'resultset_id' => $setId + 1, // Because SQL begins at 1...
                       'value'        => $value,
                       'label'        => $truncatedLabel
                    )
                );
            }
        }
    }
}
