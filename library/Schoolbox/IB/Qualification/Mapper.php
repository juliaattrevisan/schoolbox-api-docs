<?php

/**
 * IB Qualification Mapper
 * @author    [Your Name]
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\IB\Qualification;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

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
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lu_ibis_qualification');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('system_purpose', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Find all qualifications ordered by sequence
     *
     * @param array $options
     * @return GenericCollection|Model[]
     */
    public function findAllOrderedBySequence(array $options = []): GenericCollection
    {
        $options['order'] = ['sequence' => 'ASC', 'name' => 'ASC'];
        return $this->findAll($options);
    }

    /**
     * Find qualification by system purpose
     *
     * @param string $systemPurpose
     * @param array $options
     * @return GenericCollection|Model[]
     */
    public function findBySystemPurpose(string $systemPurpose, array $options = []): GenericCollection
    {
        $options['where'] = ['system_purpose' => $systemPurpose];
        return $this->findAll($options);
    }

    /**
     * Get qualification lookup data for admin lists
     *
     * @return array
     */
    public function getLookupData(): array
    {
        $qualifications = $this->findAllOrderedBySequence(['deleted' => true]);
        $lookupData = [];

        foreach ($qualifications as $qualification) {
            $lookupData[] = [
                'key' => $qualification->getId(),
                'value' => $qualification->getName(),
                'deleted' => $qualification->isDeleted(),
            ];
        }

        return $lookupData;
    }
}