<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;

/**
 * Counter class for returning counts of curriculum node usages.
 */
class Counter
{
    public function __construct(private IConnector $database)
    {
    }

    public function count(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $selectParts = [
            "
                SELECT
                    cnfc.curriculum_node_id AS id,
                    :courseDiscriminator AS discriminator,
                    COUNT(*) AS count
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id
                INNER JOIN course_specification cs ON f.id = cs.folder_id
                WHERE f.deleted_at IS NULL
                AND cs.deleted_at IS NULL
                AND cnfc.curriculum_node_id IN " . Builder::getArrayPlaceholders($ids, Cursor::DISCRIMINATOR_COURSE),
            "
                SELECT
                    cnfc.curriculum_node_id AS id,
                    :unitDiscriminator AS discriminator,
                    COUNT(*) AS count
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id
                INNER JOIN course_unit_specification cus ON f.id = cus.folder_id
                WHERE f.deleted_at IS NULL
                AND cus.deleted_at IS NULL
                AND cnfc.curriculum_node_id IN " . Builder::getArrayPlaceholders($ids, Cursor::DISCRIMINATOR_UNIT),
            "
                SELECT
                    cnfc.curriculum_node_id AS id,
                    :folderDiscriminator AS discriminator,
                    COUNT(*) AS count
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id
                LEFT JOIN course_specification cs ON f.id = cs.folder_id
                LEFT JOIN course_unit_specification cus ON f.id = cus.folder_id
                WHERE f.deleted_at IS NULL
                AND cs.id IS NULL
                AND cus.id IS NULL
                AND cnfc.curriculum_node_id IN " . Builder::getArrayPlaceholders($ids, Cursor::DISCRIMINATOR_FOLDER),
        ];
        $queryString = '(' . implode(') UNION (', $selectParts) . ')';
        $query  = $this->database->prepareQuery($queryString);
        $params = [
            'courseDiscriminator' => Cursor::DISCRIMINATOR_COURSE,
            'unitDiscriminator'   => Cursor::DISCRIMINATOR_UNIT,
            'folderDiscriminator' => Cursor::DISCRIMINATOR_FOLDER,
        ];
        $params += Builder::getArrayParameters($ids, Cursor::DISCRIMINATOR_COURSE);
        $params += Builder::getArrayParameters($ids, Cursor::DISCRIMINATOR_UNIT);
        $params += Builder::getArrayParameters($ids, Cursor::DISCRIMINATOR_FOLDER);
        $query->execute($params);

        $result = array_fill_keys(
            $ids,
            [
                Cursor::DISCRIMINATOR_COURSE => 0,
                Cursor::DISCRIMINATOR_UNIT   => 0,
                Cursor::DISCRIMINATOR_FOLDER => 0,
            ]
        );
        foreach ($query->fetchAll() as $row) {
            if (isset($row['id'])) {
                $result[$row['id']][$row['discriminator']] += $row['count'];
            }
        }
        return $result;
    }
}
