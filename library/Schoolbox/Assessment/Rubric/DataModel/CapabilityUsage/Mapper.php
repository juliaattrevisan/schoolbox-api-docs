<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityUsage;

use PDOStatement;
use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as CapabilityMapper;

/**
 * Mapper for usage of a rubric capability on a due work.
 *
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 * @method Model[]|Schoolbox\GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('rubric_capability_usage');
        $this->hasModel(Model::class);
        $this->uses('max_value', self::TYPE_DECIMAL);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins(
            'rubric_capability_id',
            CapabilityMapper::class,
            'id',
            null,
            null,
            'capability',
            'capability'
        );
        $this->joins(
            'submission_box_id',
            SubmissionBoxMapper::class,
            'id',
            null,
            null,
            'submissionBox',
            'submission_box'
        );
    }


    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('submission_box', 'sb')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('submission_box', '', 'sb', true)}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): Schoolbox\GenericCollection
    {
        /** @var SubmissionBoxMapper $boxMapper */
        $boxMapper  = $this->getJoinMapper('submission_box');
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            $usage = $this->hydrate($row);
            $usage->setSubmissionBox($boxMapper->hydrate($row, 'sb'));
            $collection->add($usage);
        }
        return $collection;
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null): bool
    {
        return true;
    }
}
