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
 * @method Criterion createModel()
 * @method Criterion findById($id)
 */
class CriterionMapper extends Schoolbox\Mapper {

    protected function mapping() {
        $this->hasTable('rubric_criterion');
        $this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\Rubric\\Criterion');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'grouping',
            'property'   => 'grouping',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'name',
            'property'   => 'name',
            'type'       => self::TYPE_STRING,
        ));
        // Parent ID can reference any of:
        // submission, category, curriculum
        // as far as I can tell, it's never not "category"
        $this->hasMap(array(
            'column'     => 'parent_id',
            'property'   => 'parentId',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'parent_type',
            'property'   => 'parentType',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deletedAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasSoftDeleteDateField('deleted_at');
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyUniqueness']);
    }

    /**
     * Clones rubric criteria from one category to another.
     *
     * @param int $from_id The category to copy from.
     * @param int $to_id   The category to copy to.
     *
     * @return int[] A list of new rubric IDs
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
}
