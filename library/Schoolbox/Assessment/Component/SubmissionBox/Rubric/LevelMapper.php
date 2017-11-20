<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;

/**
 * Class LevelMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Level hydrate(array $row, $alias = '', $callback = null)
 * @method Level createModel($idValue = null)
 * @method Level findById($id)
 * @method Collection|Level[] findByColumn($columnName, $columnValue, $options = array())
 * @method Collection|Level[] findByColumnList($columnList, $options = array())
 */
class LevelMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasModel(Level::class);

        $this->hasTable('rubric_level');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);

        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox', 'box');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteDescriptors']);
    }


    /**
     * Soft-delete callback to delete any descriptors for this level
     *
     * @param Level $level
     * @return bool
     */
    public function softDeleteDescriptors(Level $level)
    {
        DescriptorMapper::getInstance()->deleteByColumn('level_id', $level->getId());
        return true;
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('box', 'b')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('box', null, 'b', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($r = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($r);
            /** @var SubmissionBox $box */
            $box = $this->hydrateJoin($r, 'box', 'b');
            $model->setSubmissionBox($box);

            $c->add($model);
        }

        return $c;
    }


    /**
     * Clones levels from one submission box to another.
     *
     * @param int $fromId The ID of the submission box to copy from.
     * @param int $toId   The ID of the submission box to copy to.
     *
     * @return int[] A list of new rubric levels
     * @deprecated
     * @see RubricMapper::copyRubric()
     */
    public function copyFromCopiedSubmissionBox($fromId, $toId)
    {
        $levels = $this->createCollection();
        foreach ($this->findByColumn('box_id', $fromId) as $level) {
            // Don't recopy a level that already exists in $toId
            $levelCount = $this->countByColumnList([
                'box_id'   => $toId,
                'sequence' => $level->getSequence()
            ]);
            if (!$levelCount) {
                $level->getSubmissionBox()->setId($toId);
                $levels->add($level);
            }
        }
        return $this->create($levels);
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
