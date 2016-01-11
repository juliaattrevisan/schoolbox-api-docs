<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Rubric as Core;
use Schoolbox\Mapper;
use Schoolbox\Collection;
use Schoolbox\User;

class Rubric extends Core\Rubric
{

    /** @var SubmissionBox */
    protected $box;


    /**
     * @param SubmissionBox $submissionBox
     */
    public function __construct($submissionBox = null)
    {
        $id = $submissionBox ? $submissionBox->getId() : null;

        parent::__construct($id, false);
        $this->setSubmissionBox($submissionBox);
    }


    /**
     * @param SubmissionBox $box
     * @return self
     */
    public function setSubmissionBox($box)
    {
        $this->box = $box;
        return $this;
    }


    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->box;
    }


    /**
     * Returns a collection containing all the criteria in this grid, for DB persistence.
     *
     * @return Criterion[]|Collection
     */
    public function getORMCriteria()
    {
        $c = new Collection();

        // cast our object hierarchy into the flattened ORM-style models
        foreach ($this->criteriaGroups as $group) {
            foreach ($group->getAllCriteria() as $criterion) {
                $c->add($this->createORMCriterion($criterion, $group->getName()));
            }
        }

        return $c;
    }


    /**
     * @param Core\Criteria\Criterion $criterion
     * @param string                  $groupName
     * @return Criterion
     */
    protected function createORMCriterion($criterion, $groupName = '')
    {
        try {
            $model    = CriterionMapper::getInstance()->findById($criterion->getId());
            $parentId = $model->getParentId();

        } catch (Mapper\Exception $e) {
            $model    = CriterionMapper::getInstance()->createModel();
            $parentId = $this->box->getCategory()->getId();
        }

        $model->setName($criterion->getName())
              ->setGrouping($groupName ?: $criterion->getGroup()->getName())
              ->setParentType('category')
              ->setParentId($parentId);

        return $model;
    }


    /**
     * Returns a collection containing all the criteria in this grid, for DB persistence.
     *
     * @return Usage[]|Collection
     */
    public function getORMUsages()
    {
        $c = new Collection();

        $sequence = 0;
        foreach ($this->criteriaGroups as $group) {
            foreach ($group->getAllCriteria() as $criterion) {
                if ($criterion->getAllDescriptors()->count()) {
                    $c->add($this->createORMUsage($criterion, ++$sequence));
                }
            }
        }

        return $c;
    }


    /**
     * @param Criterion $criterion
     * @param int $sequence
     * @return Usage
     */
    protected function createORMUsage($criterion, $sequence)
    {
        return UsageMapper::getInstance()
            ->createModel()
            ->setCriterion($criterion)
            ->setSubmissionBox($this->box)
            ->setSequence($sequence);
    }


    /**
     * Returns a collection containing all the levels in this grid, for DB persistence.
     *
     * @return Collection|Level[]
     */
    public function getORMLevels()
    {
        $c = new Collection();

        $sequence = 0;
        foreach ($this->levels as $level) {
            $c->add($this->createORMLevel($level, ++$sequence));
        }

        return $c;
    }


    /**
     * @param Core\Competency\Level $level
     * @param int              $sequence
     * @return Level
     */
    protected function createORMLevel($level, $sequence)
    {
        return LevelMapper::getInstance()
            ->createModel()
            ->setId($level->getId())
            ->setName($level->getName())
            ->setSubmissionBox($this->box)
            ->setSequence($sequence);
    }


    /**
     * Returns a collection containing all the descriptors in this grid, for DB persistence.
     *
     * @return Descriptor[]|Collection
     */
    public function getORMDescriptors()
    {
        $c = new Collection();

        foreach ($this->levels as $sequence => $level) {
            foreach ($level->getAllDescriptors() as $descriptor) {
                $c->add($this->createORMDescriptor($descriptor, $level, $sequence));
            }
        }

        return $c;
    }


    /**
     * @param Core\Descriptor       $descriptor
     * @param Core\Competency\Level $level
     * @param int                   $sequence
     * @return Descriptor
     */
    protected function createORMDescriptor($descriptor, $level, $sequence)
    {
        // models we need
        $critModel = $this->createORMCriterion($descriptor->getCriterion());
        $lvlModel  = $this->createORMLevel($level, $sequence);

        $map = DescriptorMapper::getInstance();

        try {
            // this is a unique index
            $model = $map->findOneByColumnList([
                'criterion_id'      => $critModel->getId(),
                'submission_box_id' => $this->getSubmissionBox()->getId(),
                'level_id'          => $lvlModel->getId()
            ]);

        } catch (Mapper\Exception $e) {
            // that intersection doesn't exist, so we'll use a fresh model
            $model = $map->createModel();
        }

        return $model
            ->setCriterion($critModel)
            ->setSubmissionBox($this->box)
            ->setDescription($descriptor->getText())
            ->setValue($descriptor->getValue())
            ->setLevel($lvlModel)
            ->setValid(true);
    }


    /**
     * Be careful with this. Any manipulations you make to the descriptors will be reflected INSIDE the grid
     *
     * @return Collection|Core\Descriptor[]
     */
    public function getAllDescriptors()
    {
        $c = new Collection();

        foreach ($this->levels as $level) {
            $c->merge($level->getAllDescriptors());
        }

        return $c;
    }


    /**
     * @param User $student
     * @param Alaress_Date_Format $timestamp
     * @return Collection|Mark[]
     */
    public function getORMMarks($student, $timestamp)
    {
        $c = new Collection();

        foreach ($this->getAllDescriptors() as $descr) {
            if ($descr->isSelected()) {
                $c->add($this->createORMMark($descr, $timestamp, $student));
            }
        }

        return $c;
    }


    /**
     * @param Core\Descriptor         $descriptor
     * @param Alaress_Date_Format     $timestamp
     * @param User                    $student
     * @return Mark
     */
    protected function createORMMark($descriptor, $timestamp, $student)
    {
        return MarkMapper::getInstance()
            ->createModel()
            ->setCreatedAt($timestamp)
            ->setSubmissionBox($this->getSubmissionBox())
            ->setDescriptor($this->createORMDescriptor($descriptor, $descriptor->getLevel(), 0))
            ->setValid(true)
            ->setMark(0)
            ->setStudent($student)
            ->setCriterion($this->createORMCriterion($descriptor->getCriterion()))
        ;
    }


    /**
     * Return the Twig representation of the data structure this rubric contains
     *
     * @return array
     */
    public function toContext()
    {
        $context = parent::toContext();

        // if the box doesn't mark by group, then we need to remove the group subtotals from the context
        if (!$this->getSubmissionBox()->isMarkedPerRubricGroup()) {

            foreach ($context['groups'] as $groupId => $group) {
                unset(
                    $context['groups'][$groupId]['sum'],
                    $context['groups'][$groupId]['total'],
                    $context['groups'][$groupId]['percentage']
                );
            }

        }


        return $context;
    }


}
