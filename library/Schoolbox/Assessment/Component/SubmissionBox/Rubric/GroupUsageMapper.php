<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDOStatement;
use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

/**
 * Mapper for usage of a rubric group on a due work.
 *
 * @method GroupUsage hydrate(array $row, $alias = '', $callback = null)
 * @method GroupUsage createModel($idValue = null)
 * @method GroupUsage[]|Schoolbox\GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class GroupUsageMapper extends Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('rubric_group_usage');
        $this->hasModel(GroupUsage::class);
        $this->uses('max_value', self::TYPE_DECIMAL);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins(
            'rubric_group_id',
            GroupMapper::class,
            'id',
            null,
            null,
            'group',
            'group'
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
