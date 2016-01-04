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

class DescriptorMapper extends Schoolbox\Mapper {

    protected function mapping() {
        $this->hasTable('rubric_descriptor');
        $this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\Rubric\\Descriptor');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->hasMap(array(
            'column'     => 'criterion_id',
            'property'   => 'criterion',
            'type'       => self::TYPE_MODEL,
            'join'       => 'criterion',
        ));
        $this->hasMap(array(
            'column'     => 'submission_box_id',
            'property'   => 'submissionBox',
            'type'       => self::TYPE_MODEL,
            'join'       => 'submission_box',
        ));
        $this->hasMap(array(
            'column'     => 'value',
            'property'   => 'value',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'valid',
            'property'   => 'valid',
            'type'       => self::TYPE_BOOLEAN,
        ));

        $this->hasJoin(
            'criterion',
            array(
                'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\Rubric\\CriterionMapper',
                'this_key'  => 'criterion_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'submission_box',
            array(
                'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBoxMapper',
                'this_key'  => 'submission_box_id',
                'other_key' => 'id',
            )
        );
    }

    /**
     * Clones descriptors from one submission box to another.
     *
     * @param int $from_id The ID of the submission box to copy from.
     * @param int $to_id   The ID of the submission box to copy to.
     *
     * @return int[] A list of new rubric descriptors
     */
    public function copyFromCopiedSubmissionBox($from_id, $to_id) {

        // Get all descriptors and criteria for the from box
        $query = "
            SELECT
                {$this->getColumnString('rd')},
                {$this->getJoinColumnString('criterion', 'rc')}
            FROM `{$this->getTable()}` `rd`
            INNER JOIN {$this->getJoinSql('criterion', 'rd', 'rc')}
            WHERE `rd`.`submission_box_id` = ?
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($from_id));
        /** @var Descriptor[] $fromList */
        $fromList = $this->hydrateList($statement, 'rd', function($mapper, $model, $row) {
            /** @var Descriptor $model */
            $model->setCriterion($mapper->getJoinMapper('criterion')->hydrate($row, 'rc'));
            return $model;
        });

        // And copy each descriptor in the from box into the to box
        $toBox  = $this->getJoinMapper('submission_box')->findById($to_id);
        $toList = new Schoolbox\Collection();
        foreach ($fromList as $fromItem) {
            // The to box descriptor's criterion:
            // has the same name, type and grouping as the to box, but instead
            // belongs to the to box's category.
            try {
                $params = array(
                    'parent_id'   => $toBox->getCategory()->getId(),
                    'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
                    'name'        => $fromItem->getCriterion()->getName(),
                    'grouping'    => $fromItem->getCriterion()->getGrouping(),
                );
                $toCriterion = $this->getJoinMapper('criterion')->findOneByColumnList($params);
                $toItem = clone $fromItem;
                $toItem
                    ->setSubmissionBox($toBox)
                    ->setCriterion($toCriterion);
                $toList->add($toItem);
            }
            catch (Schoolbox\Mapper\Exception $e) {
                // Add this to the list of exceptions somewhere but don't do anything.
            }
        }
        return $this->save($toList);
    }

}
