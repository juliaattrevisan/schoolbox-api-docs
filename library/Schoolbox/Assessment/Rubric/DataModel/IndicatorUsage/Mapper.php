<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\IndicatorUsage;

use PDOStatement;
use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as IndicatorMapper;

/**
 * Mapper for rubric indicator usage.
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 * @method Model[]|Schoolbox\GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends Schoolbox\Mapper
{
    protected function mapping()
    {
        $this->hasTable('rubric_indicator_usage');
        $this->hasModel(Model::class);
        $this->uses('max_value', self::TYPE_DECIMAL);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('rubric_indicator_id', IndicatorMapper::class, 'id', null, null, 'indicator', 'indicator');
        $this->joins('submission_box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox', 'submission_box');

        // Indicator usages are hard-deleted, so we have to use CALLBACK_POSTDELETE not CALLBACK_POSTSOFTDELETE
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'toggleUseRubric']);
    }

    /**
     * @param Model $indicatorUsage
     */
    public function toggleUseRubric(Model $indicatorUsage)
    {
        $currentBoxId = $indicatorUsage->getSubmissionBox()->getId();
        $indicatorUsagesForSubmissionBox = $this->findByColumn('submission_box_id', $currentBoxId);
        if ($indicatorUsagesForSubmissionBox->count() === 0) {
            // No more indicator usages on this box: turn off Use Rubric
            $query = 'UPDATE submission_box SET use_rubric = 0 WHERE submission_box.id = ?';
            $statement = $this->getDatabase()->prepare($query);
            $statement->execute([$currentBoxId]);
        }
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
     * Retrieves a list of assessment IDs and names in which this indicator is used.
     *
     * This does not include deleted assessments.
     *
     * @param  int $indicatorId
     * @return array[] id: the assessment's ID, title: the assessment's title, 'submission_box_id': the box's id
     */
    public function findIndicatorUsage($indicatorId): array
    {
        $query = "
            SELECT DISTINCT
                a.id,
                a.title,
                submission_box_id
            FROM {$this->getTable()} ru
            INNER JOIN {$this->getJoinSql('submission_box', 'ru', 'sb')}
            INNER JOIN {$this->getJoinSql('submission_box.assessmentModel', 'sb', 'a')}
            WHERE ru.rubric_indicator_id = ?
            AND sb.use_rubric IS TRUE
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([$indicatorId]);
        $result = [];
        foreach ($statement as $row) {
            $result[$row['id']] = $row;
        }
        return $result;
    }


    /**
     * Retrieves a list of assessment IDs and names in which this capability is used.
     *
     * This does not include deleted assessments.
     *
     * @param  int $capabilityId
     * @return array[] id: the assessment's ID, title: the assessment's title, 'submission_box_id': the box's id
     */
    public function findCapabilityUsage($capabilityId): array
    {
        $query = "
            SELECT DISTINCT
                a.id,
                a.title,
                submission_box_id
            FROM {$this->getTable()} ru
            INNER JOIN {$this->getJoinSql('submission_box', 'ru', 'sb')}
            INNER JOIN {$this->getJoinSql('submission_box.assessmentModel', 'sb', 'a')}
            INNER JOIN {$this->getJoinSql('indicator', 'ru', 'rc')}
            WHERE rc.rubric_capability_id = ?
            AND sb.use_rubric IS TRUE
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([$capabilityId]);
        $result = [];
        foreach ($statement as $row) {
            $result[$row['id']] = $row;
        }
        return $result;
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
