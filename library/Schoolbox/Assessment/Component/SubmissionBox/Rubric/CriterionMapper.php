<?php
/**
 * Mapper for rubric criteria.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox;

/**
 * Class CriterionMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Criterion createModel($idValue = null)
 * @method Criterion findById($id)
 * @method Criterion[] findByColumnList($columnList, $options = array())
 * @method Criterion findOneByColumnList($columnList, $order = [], $options = [])
 */
class CriterionMapper extends Schoolbox\Mapper {

    protected function mapping() {
        $this->hasTable('rubric_criterion');
        $this->hasModel(Criterion::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('grouping', self::TYPE_STRING);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('parent_type', self::TYPE_STRING);
        // Parent ID can reference any of:
        // submission, category, curriculum
        // as far as I can tell, it's never not "category"
        $this->uses('parent_id', self::TYPE_INTEGER);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyUniqueness']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteDescriptors']);
    }


    /**
     * Soft-delete callback to delete any descriptors for this criterion
     *
     * @param Criterion $criterion
     * @return bool
     */
    public function softDeleteDescriptors(Criterion $criterion)
    {
        DescriptorMapper::getInstance()->deleteByColumn('criterion_id', $criterion->getId());
        return true;
    }


    /**
     * Clones rubric criteria from one category to another.
     *
     * @param int $from_id The category to copy from.
     * @param int $to_id   The category to copy to.
     *
     * @return int[] A list of new rubric IDs
     * @deprecated
     * @see RubricMapper::copyRubric()
     */
    public function copyFromCategory($from_id, $to_id) {
        /** @var Criterion[] $from_rubric_list */
        $from_rubric_list = $this->findByColumnList(
            array(
                'parent_id'   => $from_id,
                'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
            )
        );
        /** @var Criterion[] $existing_rubric_list */
        $existing_rubric_list = $this->findByColumnList(
            array(
                'parent_id'   => $to_id,
                'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
            )
        );
        $new_rubric_list = new Schoolbox\Collection();
        foreach ($from_rubric_list as $rubric) {
            // search existing for a rubric that matches this one.
            // If there's not one, then save this rubric.
            $rubric_exists = false;
            foreach ($existing_rubric_list as $existing_rubric) {
                if (
                    $existing_rubric->getGrouping() === $rubric->getGrouping()
                    && $existing_rubric->getName() === $rubric->getName()
                ) {
                    $rubric_exists = true;
                    break;
                }
            }
            if (!$rubric_exists) {
                $new_rubric = clone $rubric;
                $new_rubric->setId(null)->setParentId($to_id);
                $new_rubric_list->add($new_rubric);
            }
        }
        return $this->save($new_rubric_list);
    }

    /**
     * Pre-save callback - if a copy of this criterion already exists, then
     * update it instead of saving a new one.
     *
     * This replaces the unique index on this table, which needs to be removed
     * because MySQL max index size doesn't allow more than about ~330 UTF-8
     * characters.
     *
     * @param  Criterion $object
     * @return bool      Always true
     */
    public function verifyUniqueness(Criterion $object)
    {
        // remove any newlines and consecutive/leading/trailing spaces from input
        $object->setName(preg_replace('`\s+`', ' ', trim($object->getName())));
        $object->setGrouping(preg_replace('`\s+`', ' ', trim($object->getGrouping())));

        try {
            // attempt lookup
            $existingObject = $this->findOneByColumnList([
                'grouping'    => $object->getGrouping(),
                'name'        => $object->getName(),
                'parent_id'   => $object->getParentId(),
                'parent_type' => $object->getParentType(),
            ]);

            // found something! sweet. assign that ID.
            $object->setId($existingObject->getId());
        } catch (Schoolbox\Mapper\Exception $e) {
            // noop, new object. leave it as-is
        }
        return true;
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
