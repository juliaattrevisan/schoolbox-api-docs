<?php

/**
 * Rubrics Mapper
 *
 * @author    Tom McDonnell <tom.mcdonnell@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Rubrics;

use Exception;
use http\Exception\InvalidArgumentException;
use PDO;
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
     * @return array
     */
    public function getAllUsingSimpleSelectQuery(): array
    {
        $pdoStatement = $this->database->prepare('SELECT * FROM rubric_result');
        $pdoStatement->execute();

        return $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array $items
     * @return void
     * @throws Exception
     */
    public function saveRubricResultsFromPostedForm(array $items): void
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
            // Strip any values that might have been inserted as keys.
            $results = array_values($results);

            // Do a cursory check that none of the labels are blank.
            foreach ($results as $lbl) {
                if ('' === $lbl) {
                    throw new InvalidArgumentException("Expected only non-blank labels.  Blank label found.");
                }
            }

            foreach ($results as $value => $label) {
                /* Forcibly truncate $label values, so they don't exceed Model::LABEL_MAXLENGTH chars.
                 * This is the maximum width allowed in our db schema and will cause sentry errors
                 * unless we forcibly ensure that it doesn't exceed the space available
                 */
                $truncatedLabel = mb_substr($label, 0, \Schoolbox\Assessment\Rubric\DataModel\Result\Model::LABEL_MAXLENGTH, 'UTF-8');

                $stmt->execute(
                    array(
                       'resultset_id' => $setId + 1, // because SQL begins at 1...
                       'value'        => $value,
                       'label'        => $truncatedLabel
                    )
                );
            }
        }
    }
}
