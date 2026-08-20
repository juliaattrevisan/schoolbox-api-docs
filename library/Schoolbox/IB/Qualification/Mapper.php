<?php

/**
 * IB Qualification Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Qualification;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\IB\SystemPurpose\Mapper as SystemPurposeMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\IB\Qualification
 */
class Mapper extends BaseMapper
{
    /**
     * IB qualifications to create when IB is enabled.
     *
     * Existing active qualifications are matched by system purpose, so a "Diploma Programme" qualification already
     * linked to the "Diploma Programme Year 2" system purpose is treated as present.
     *
     * @var array<int, array{qualificationName: string, systemPurposeName: string, categoryName: string}>
     */
    private const array IB_QUALIFICATIONS = [
        [
            'qualificationName' => 'Diploma Programme',
            'systemPurposeName' => 'Diploma Programme Year 2',
            'categoryName' => 'DIPLOMA',
        ],
        [
            'qualificationName' => 'Diploma Course',
            'systemPurposeName' => 'Diploma Course',
            'categoryName' => 'COURSE',
        ],
    ];

    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('lu_qualification');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->joins('system_purpose_id', SystemPurposeMapper::class, 'id', null, null, 'systemPurpose', 'system_purpose');
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return [
            'sequence' => 'ASC',
            'name' => 'ASC',
        ];
    }

    /**
     * Create the required IB qualifications if they do not already exist.
     *
     * @return void
     */
    public function ensureIbQualificationsExist(): void
    {
        $query = "
            INSERT INTO `lu_qualification` (`name`, `system_purpose_id`, `sequence`)
            SELECT :qualificationName, sp.`id`, (SELECT COALESCE(MAX(`sequence`), 0) + 1 FROM `lu_qualification`)
            FROM `ib_system_purposes` sp
            INNER JOIN `lu_ib_category` c ON c.`id` = sp.`category_id`
            WHERE sp.`name` = :systemPurposeName
              AND c.`name` = :categoryName
              AND NOT EXISTS (
                  SELECT 1
                  FROM `lu_qualification` q
                  WHERE q.`system_purpose_id` = sp.`id`
                    AND q.`deleted_at` IS NULL
              )
        ";

        $statement = $this->getDatabase()->prepareQuery($query);

        foreach (self::IB_QUALIFICATIONS as $qualification) {
            $statement->execute($qualification);
        }
    }
}
