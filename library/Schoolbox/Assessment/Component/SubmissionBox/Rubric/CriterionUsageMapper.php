<?php
/**
 * Mapper for rubric criterion usage.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDOStatement;
use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

/**
 * Class CriterionUsageMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method CriterionUsage hydrate(array $row, $alias = '', $callback = null)
 * @method CriterionUsage createModel($idValue = null)
 * @method CriterionUsage[]|Schoolbox\GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class CriterionUsageMapper extends Schoolbox\Mapper
{
    protected function mapping()
    {
        $this->hasTable('rubric_criterion_usage');
        $this->hasModel(CriterionUsage::class);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('criterion_id', CriterionMapper::class, 'id', null, null, 'criterion', 'criterion');
        $this->joins('submission_box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox', 'submission_box');
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
     * Retrieves a list of assessment IDs and names in which this criterion is used.
     *
     * This does not include deleted assessments.
     *
     * @param  int $criterionId
     * @return array[] id: the assessment's ID, title: the assessment's title, 'submission_box_id': the box's id
     */
    public function findCriterionUsage($criterionId): array
    {
        $query = "
            SELECT DISTINCT
                a.id,
                a.title,
                submission_box_id
            FROM {$this->getTable()} ru
            INNER JOIN {$this->getJoinSql('submission_box', 'ru', 'sb')}
            INNER JOIN {$this->getJoinSql('submission_box.assessmentModel', 'sb', 'a')}
            WHERE ru.criterion_id = ?
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([$criterionId]);
        $result = [];
        foreach ($statement as $row) {
            $result[$row['id']] = $row;
        }
        return $result;
    }


    /**
     * Retrieves a list of assessment IDs and names in which this group is used.
     *
     * This does not include deleted assessments.
     *
     * @param  int $groupId
     * @return array[] id: the assessment's ID, title: the assessment's title, 'submission_box_id': the box's id
     */
    public function findGroupUsage($groupId): array
    {
        $query = "
            SELECT DISTINCT
                a.id,
                a.title,
                submission_box_id
            FROM {$this->getTable()} ru
            INNER JOIN {$this->getJoinSql('submission_box', 'ru', 'sb')}
            INNER JOIN {$this->getJoinSql('submission_box.assessmentModel', 'sb', 'a')}
            INNER JOIN {$this->getJoinSql('criterion', 'ru', 'rc')}
            WHERE rc.rubric_group_id = ?
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([$groupId]);
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
