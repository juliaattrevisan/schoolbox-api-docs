<?php
/**
 * Mapper for rubric usage.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

/**
 * Class UsageMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Usage createModel($idValue = null)
 * @method Usage[]|Schoolbox\GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class UsageMapper extends Schoolbox\Mapper {

    protected function mapping() {
        $this->hasTable('rubric_usage');
        $this->hasModel(Usage::class);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('criterion_id', CriterionMapper::class, 'id', null, null, 'criterion', 'criterion');
        $this->joins('submission_box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox', 'submission_box');
    }

    /**
     * Clones rubric usage from one submission box to another.
     *
     * @param int $from_id The ID of the submission box to copy from.
     * @param int $to_id   The ID of the submission box to copy to.
     *
     * @return int[] A list of new rubric usages
     * @deprecated
     * @see RubricMapper::copyRubric()
     */
    public function copyFromCopiedSubmissionBox($from_id, $to_id) {

        // Load list of criteria to load from and to.
        /** @var CriterionMapper $criterion_mapper */
        $criterion_mapper = $this->getJoinMapper('criterion');
        $query = "
            SELECT
                {$this->getColumnString('ru')},
                {$this->getJoinColumnString('criterion', 'rc')}
            FROM `{$this->getTable()}` `ru`
            INNER JOIN {$this->getJoinSql('criterion', 'ru', 'rc')}
            WHERE `ru`.`submission_box_id` = ?
        ";
        $statement = $this->getDatabase()->prepare($query);

        $statement->execute(array($from_id));
        /** @var Criterion[] $from_criterion_list */
        $from_criterion_list = $criterion_mapper->hydrateList($statement, 'rc');

        $statement->execute(array($to_id));
        /** @var Criterion[] $to_criterion_list */
        $to_criterion_list = $criterion_mapper->hydrateList($statement, 'rc');

        // Load the submission box
        $box_mapper = $this->getJoinMapper('submission_box');
        /** @var SubmissionBox $to_box */
        $to_box = $box_mapper->findById($to_id);

        // Search for list of rubrics to load.
        $new_criterion_list = new Schoolbox\Collection();
        foreach ($from_criterion_list as $from_criterion) {
            $is_used = false;
            foreach ($to_criterion_list as $to_criterion) {
                if (
                    $from_criterion->getGrouping() === $to_criterion->getGrouping()
                    && $from_criterion->getName() === $to_criterion->getName()
                ) {
                    $is_used = true;
                    break;
                }
            }
            if (!$is_used) {
                $criterion_usage = new Usage();
                $criterion_usage->setSubmissionBox($to_box);
                // Find the criterion to link this one to.
                try {
                    $criterion_usage->setCriterion(
                        $criterion_mapper->findOneByColumnList(
                            array(
                                'grouping'    => $from_criterion->getGrouping(),
                                'name'        => $from_criterion->getName(),
                                'parent_id'   => $to_box->getCategory()->getId(),
                                'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
                            )
                        )
                    );
                    $new_criterion_list->add($criterion_usage);
                }
                catch (Schoolbox\Mapper\Exception $e) {
                    // Add this to the list of exceptions somewhere but don't do anything.
                }
            }
        }
        return $this->save($new_criterion_list);
    }

    /**
     * Retrieves a list of assessment IDs and names in which this criterion is used.
     *
     * This does not include deleted assessments.
     *
     * @param  int $criterionId
     * @return array[] id: the assessment's ID, title: the assessment's title, 'submission_box_id': the box's id
     */
    public function findUsage($criterionId)
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
        return $statement->fetchAll();
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
