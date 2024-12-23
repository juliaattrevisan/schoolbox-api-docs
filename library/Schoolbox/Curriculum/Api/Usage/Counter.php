<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Schoolbox\Config\ConfigMapper;

/**
 * Counter class for fetching counts of curriculum node usages.
 */
class Counter
{
    /**
     * Constructor
     *
     * @param IConnector $database The database connection to fetch from.
     */
    public function __construct(private IConnector $database, private ConfigMapper $configMapper)
    {
    }

    /**
     * Fetch counts of curriculum node usages for the given node IDs.
     *
     * @param array<int> $ids A set of nodes to fetch usages for
     * @return array<int, array<string, int>> key: curriculum node ID,
     *                                        value: array with key: discriminator, value: count for that discriminator
     */
    public function findUsageCounts(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Sub-queries for fetching the counts of each discriminator:
        // these get joined together into a single UNION statement
        $selectParts = [
            "
                SELECT
                    cnfc.curriculum_node_id AS id,
                    :courseDiscriminator AS discriminator,
                    COUNT(*) AS count
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id AND f.deleted_at IS NULL
                INNER JOIN course_specification cs ON f.id = cs.folder_id AND cs.deleted_at IS NULL
                WHERE cnfc.curriculum_node_id IN "
                . Builder::getArrayPlaceholders($ids, Cursor::DISCRIMINATOR_COURSE)
                . " GROUP BY id, discriminator",
            "
                SELECT
                    cnfc.curriculum_node_id AS id,
                    :unitDiscriminator AS discriminator,
                    COUNT(*) AS count
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id AND f.deleted_at IS NULL
                INNER JOIN course_unit_specification cus ON f.id = cus.folder_id AND cus.deleted_at IS NULL
                WHERE cnfc.curriculum_node_id IN "
                . Builder::getArrayPlaceholders($ids, Cursor::DISCRIMINATOR_UNIT)
                . " GROUP BY id, discriminator",
            "
                SELECT
                    cnfa.curriculum_node_id AS id,
                    :assessmentDiscriminator AS discriminator,
                    COUNT(*) AS count
                FROM curriculum_node_for_assessment cnfa
                INNER JOIN mv_assessment_relation var ON cnfa.assessment_id = var.assessment_id
                INNER JOIN assessment a ON var.assessment_id = a.id AND a.deleted_at IS NULL
                INNER JOIN course_unit_specification cus
                    ON var.course_unit_specification_id = cus.id AND cus.deleted_at IS NULL
                WHERE cnfa.curriculum_node_id IN "
            . Builder::getArrayPlaceholders($ids, Cursor::DISCRIMINATOR_ASSESSMENT)
            . " GROUP BY id, discriminator",
            "
                SELECT
                    cnfc.curriculum_node_id AS id,
                    :folderDiscriminator AS discriminator,
                    COUNT(*) AS count
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id AND f.deleted_at IS NULL
                LEFT JOIN course_specification cs ON f.id = cs.folder_id AND cs.deleted_at IS NULL
                LEFT JOIN course_unit_specification cus ON f.id = cus.folder_id AND cus.deleted_at IS NULL
                WHERE cs.id IS NULL
                AND cus.id IS NULL
                AND cnfc.curriculum_node_id IN "
                . Builder::getArrayPlaceholders($ids, Cursor::DISCRIMINATOR_FOLDER)
                . " GROUP BY id, discriminator",
            " SELECT
                    cb.curriculum_node_id AS id,
                    :badgeDiscriminator AS discriminator,
                    COUNT(*) AS count
                FROM curriculum_badge cb
                JOIN badge b on (cb.badge_id = b.id AND b.deleted_at IS NULL)
                WHERE cb.curriculum_node_id IN "
            . Builder::getArrayPlaceholders($ids, Cursor::DISCRIMINATOR_BADGE)
            . " AND (
                    SELECT value FROM {$this->configMapper->getTable()} WHERE name = :badgesEnabled AND value = 1
                ) IS NOT NULL"
            . " GROUP BY cb.curriculum_node_id, discriminator",
        ];
        $queryString = '(' . implode(') UNION ALL (', $selectParts) . ')';
        $query       = $this->database->prepareQuery($queryString);

        // Parameters for the query
        $params = [
            'courseDiscriminator'     => Cursor::DISCRIMINATOR_COURSE,
            'unitDiscriminator'       => Cursor::DISCRIMINATOR_UNIT,
            'assessmentDiscriminator' => Cursor::DISCRIMINATOR_ASSESSMENT,
            'folderDiscriminator'     => Cursor::DISCRIMINATOR_FOLDER,
            'badgeDiscriminator'      => Cursor::DISCRIMINATOR_BADGE,
            'badgesEnabled'           => $this->configMapper::NAME_BADGES_ENABLED,
        ];
        $params += Builder::getArrayParameters($ids, Cursor::DISCRIMINATOR_COURSE);
        $params += Builder::getArrayParameters($ids, Cursor::DISCRIMINATOR_UNIT);
        $params += Builder::getArrayParameters($ids, Cursor::DISCRIMINATOR_ASSESSMENT);
        $params += Builder::getArrayParameters($ids, Cursor::DISCRIMINATOR_FOLDER);
        $params += Builder::getArrayParameters($ids, Cursor::DISCRIMINATOR_BADGE);

        // Run query and compile result
        $result = array_fill_keys(
            $ids,
            [
                Cursor::DISCRIMINATOR_COURSE     => 0,
                Cursor::DISCRIMINATOR_UNIT       => 0,
                Cursor::DISCRIMINATOR_ASSESSMENT => 0,
                Cursor::DISCRIMINATOR_FOLDER     => 0,
                Cursor::DISCRIMINATOR_BADGE      => 0,
            ]
        );
        $query->execute($params);
        foreach ($query->fetchAll() as $row) {
            if (isset($row['id'])) {
                $result[$row['id']][$row['discriminator']] += $row['count'];
            }
        }
        return $result;
    }
}
