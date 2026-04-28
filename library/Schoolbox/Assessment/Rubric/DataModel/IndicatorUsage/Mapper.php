<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\IndicatorUsage;

use PDOStatement;
use Schoolbox\Assessment\Rubric\DataModel\Capability;
use Schoolbox\Assessment\Rubric\DataModel\Indicator;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for rubric indicator usage.
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 * @method GenericCollection<Model> findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('rubric_indicator_usage');
        $this->hasModel(Model::class);
        $this->uses('max_value', self::TYPE_DECIMAL);
        $this->uses('sequence', self::TYPE_INTEGER);

        $this->joins(
            'rubric_indicator_id',
            Indicator\Mapper::class,
            'id',
            null,
            null,
            'indicator',
            'indicator'
        );

        $this->joins(
            'rubric_instance_id',
            \Schoolbox\Assessment\Rubric\Instance\Mapper::class,
            'id',
            null,
            null,
            'rubricInstance',
            'rubricInstance'
        );
    }

//    /**
//     * Switches off this usage's due work's rubric if there are no remaining
//     * usages of any indicator for this due work.
//     *
//     * @param Model $indicatorUsage
//     */
//    public function toggleUseRubric(Model $indicatorUsage)
//    {
        /* TODO I dont' think we'd have this toggle anymore: we'd just delete them instead?
         */
//        $currentBoxId = $indicatorUsage->getRubricInstance()->getId();
//        $indicatorUsagesForSubmissionBox = $this->findByColumn('submission_box_id', $currentBoxId);
//        if ($indicatorUsagesForSubmissionBox->count() === 0) {
//            // No more indicator usages on this box: turn off Use Rubric
//            $query = 'UPDATE submission_box SET use_rubric = 0 WHERE submission_box.id = ?';
//            $statement = $this->getDatabase()->prepare($query);
//            $statement->execute([$currentBoxId]);
//        }
//    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('rubricInstance', 'ri')},
                {$this->getJoinColumnString('indicator', 'i')},
                {$this->getJoinColumnString('indicator.capability', 'ic')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('rubricInstance', '', 'ri', true)}
            INNER JOIN {$this->getJoinSql('indicator', '', 'i', true)}
            INNER JOIN {$this->getJoinSql('indicator.capability', 'i', 'ic', true)}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            $usage = $this->hydrate($row);

            /** @var \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance */
            $rubricInstance = $this->hydrateJoin($row, 'rubricInstance', 'ri');

            /** @var Indicator\Model $indicator */
            $indicator = $this->hydrateJoin($row, 'indicator', 'i');

            /** @var Capability\Model $capability */
            $capability = $this->hydrateJoin($row, 'indicator.capability', 'ic');

            $usage
                ->setRubricInstance($rubricInstance)
                ->setIndicator($indicator->setCapability($capability));

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
     * @return array[] id: the assessment's ID, title: the assessment's title
     */
    public function findIndicatorUsage(int $indicatorId): array
    {
        $query = "
            SELECT DISTINCT
                a.id,
                a.title
            FROM {$this->getTable()} riu
            INNER JOIN {$this->getJoinSql('rubricInstance', 'riu', 'ri')}
            INNER JOIN {$this->getJoinSql('rubricInstance.assessmentModel', 'ri', 'a')}
            WHERE riu.rubric_indicator_id = :indicatorId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'indicatorId' => $indicatorId
        ]);

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
