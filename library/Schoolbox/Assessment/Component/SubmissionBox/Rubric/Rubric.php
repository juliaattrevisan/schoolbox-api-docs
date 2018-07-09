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
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric as Core;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Collection;
use Schoolbox\User;
use SplObjectStorage;

/**
 * Composed of domain-level objects. Can be decomposed into ORM mapped-models
 */
class Rubric extends Core\Rubric
{
    /**
     * The due work that this rubric is for, if there is one.
     *
     * @var SubmissionBox|null
     */
    private $box;

    /**
     * Maps domain object Criterion to ORM Criterion.
     *
     * This contains all criteria that are available to the rubric grid,
     * whether or not this rubric's box is using them.
     *
     * @var SplObjectStorage|Core\Criteria\Criterion[]
     */
    private $criterionCache;

    /**
     * Maps domain object Criterion to ORM Usage.
     *
     * This contains only the criteria this rubric's box is actually using.
     *
     * @var SplObjectStorage|Core\Criteria\Criterion[]
     */
    private $criterionUsageCache;

    /**
     * Maps domain object Group to ORM Group.
     *
     * This contains all groups that are available to the rubric grid,
     * whether or not this rubric's box is using them.
     *
     * @var SplObjectStorage|Core\Criteria\Group[]
     */
    private $groupCache;

    /**
     * Maps domain object Group to ORM Group.
     *
     * This contains only the groups this rubric's box is actually using.
     *
     * @var SplObjectStorage|Core\Criteria\Group[]
     */
    private $groupUsageCache;

    /**
     * Maps domain object Level to ORM Level.
     *
     * This contains the levels that have been assigned to this rubric's box.
     *
     * @var SplObjectStorage|Core\Competency\Level[]
     */
    private $levelCache;

    /**
     * Maps domain object Descriptor to ORM Descriptor.
     *
     * This contains all the descriptors that have been assigned to one of the
     * criteria/level combinations for the rubric's box.
     *
     * @var SplObjectStorage|Core\Descriptor[]
     */
    private $descriptorCache;

    /**
     * Maps domain object Descriptor to ORM Mark.
     *
     * This contains all rubric marks that have been assigned to a participant
     * for this rubric's box, for a particular descriptor within the rubric.
     *
     * @var SplObjectStorage|Mark[]
     */
    private $markCache;


    /**
     * @param SubmissionBox $submissionBox
     */
    public function __construct(SubmissionBox $submissionBox = null)
    {
        $id = $submissionBox ? $submissionBox->getId() : 0;

        parent::__construct($id, false);
        $this->setSubmissionBox($submissionBox);

        // setup caching
        $this->criterionCache      = new SplObjectStorage();
        $this->descriptorCache     = new SplObjectStorage();
        $this->levelCache          = new SplObjectStorage();
        $this->criterionUsageCache = new SplObjectStorage();
        $this->markCache           = new SplObjectStorage();
    }


    /**
     * @param SubmissionBox|null $box
     * @return self
     */
    public function setSubmissionBox(SubmissionBox $box = null)
    {
        $this->box = $box;
        // bind my ID to the box's ID
        if ($box) {
            $this->setId($box->getId());
        }
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
        $c = CriterionMapper::getInstance()->createCollection();

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
    protected function createORMCriterion(Core\Criteria\Criterion $criterion, $groupName = '')
    {
        if (!$this->criterionCache->contains($criterion)) {
            try {
                $model    = CriterionMapper::getInstance()->findById($criterion->getId());
                $parentId = $model->getGroup()->getParentId();

            } catch (Mapper\Exception $e) {
                $model    = CriterionMapper::getInstance()->createModel();
                $parentId = $this->box->getCategory()->getId();
                $model->setGroup(GroupMapper::getInstance()->createModel());
            }

            $model->setName($criterion->getName());
            $model->getGroup()
                  ->setName($groupName)
                  ->setParentType(Group::PARENT_TYPE_CATEGORY)
                  ->setParentId($parentId)
            ;

            $this->criterionCache->attach($criterion, $model);
        }

        $return = $this->criterionCache->offsetGet($criterion);
        $return->getGroup()->setName($groupName ?: $criterion->getGroup()->getName());
        return $return;
    }


    /**
     * Returns a collection containing all the groups in this grid, for DB persistence.
     *
     * @return Usage[]|Collection
     */
    public function getORMGroups()
    {
        $c = GroupMapper::getInstance()->createCollection();
        foreach ($this->criteriaGroups as $group) {
            $c->add($this->createORMGroup($group));
        }
        return $c;
    }


    /**
     * @param Core\Criteria\Group $group
     * @return Group
     */
    protected function createORMGroup(Core\Criteria\Group $group)
    {
        if (!$this->groupCache->contains($group)) {
            $model = GroupMapper::getInstance()->findByIdWithFallback($group->getId());

//            try {
//                $model = GroupMapper::getInstance()->findById($group->getId());
//            } catch (Mapper\Exception $e) {
//                $model = GroupMapper::getInstance()->createModel();
//            }

            $model->setName($group->getName())
                  ->setParentType(Group::PARENT_TYPE_CATEGORY)
                  ->setParentId($this->box->getCategory()->getId());
            $this->groupCache->attach($group, $model);
        }

        return $this->groupCache->offsetGet($group);
    }


    /**
     * Returns a collection containing all the criteria in this grid, for DB persistence.
     *
     * @return Usage[]|Collection
     */
    public function getORMUsages()
    {
        $c = UsageMapper::getInstance()->createCollection();

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
     * @param Core\Criteria\Criterion $criterion
     * @param int $sequence
     * @return Usage
     */
    protected function createORMUsage(Core\Criteria\Criterion $criterion, $sequence)
    {
        if (!$this->criterionUsageCache->contains($criterion)) {
            $this->criterionUsageCache->attach($criterion,
                UsageMapper::getInstance()
                  ->createModel()
                  ->setCriterion($this->criterionCache->offsetGet($criterion))
                  ->setSubmissionBox($this->box)
            );
        }

        return $this->criterionUsageCache->offsetGet($criterion)->setSequence($sequence);
    }


    /**
     * Returns a collection containing all the levels in this grid, for DB persistence.
     *
     * @return Collection|Level[]
     */
    public function getORMLevels()
    {
        $c = LevelMapper::getInstance()->createCollection();

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
    protected function createORMLevel(Core\Competency\Level $level, $sequence)
    {
        if (!$this->levelCache->contains($level)) {

            if ($level->getId()) {
                $model = LevelMapper::getInstance()->findById($level->getId());

            } else {
                try {
                    $model = LevelMapper::getInstance()->findOneByColumnList([
                        'box_id' => $this->getSubmissionBox()->getId(),
                        'sequence' => $sequence
                    ]);
                } catch (Mapper\Exception $e) {
                    $model = LevelMapper::getInstance()->createModel();
                }
            }

            $this->levelCache->attach($level, $model
                  ->setName($level->getName())
                  ->setSubmissionBox($this->box)
            );
        }
        return $this->levelCache->offsetGet($level)->setSequence($sequence);
    }


    /**
     * Returns a collection containing all the descriptors in this grid, for DB persistence.
     *
     * @return Descriptor[]|Collection
     */
    public function getORMDescriptors()
    {
        $c = DescriptorMapper::getInstance()->createCollection();

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
    protected function createORMDescriptor(Core\Descriptor $descriptor, Core\Competency\Level $level, $sequence)
    {
        if (!$this->descriptorCache->contains($descriptor)) {
            // models we need
            $critModel = $this->createORMCriterion($descriptor->getCriterion());
            $lvlModel  = $this->createORMLevel($level, $sequence);

            $mapper = DescriptorMapper::getInstance();

            try {
                // this is a unique index
                $model = $mapper->findOneByColumnList([
                    'criterion_id'      => $critModel->getId(),
                    'level_id'          => $lvlModel->getId()
                ]);

            } catch (Mapper\Exception $e) {
                // that intersection doesn't exist, so we'll use a fresh model
                $model = $mapper->createModel();
            }

            $model
                ->setCriterion($critModel)
                ->setDescription($descriptor->getText())
                ->setValue($descriptor->getValue())
                ->setLevel($lvlModel)
                ->setDeletedAt(new \Alaress_Date_NullDate());

            $this->descriptorCache->attach($descriptor, $model);
        }

        return $this->descriptorCache->offsetGet($descriptor);
    }


    /**
     * Be careful with this. Any manipulations you make to the descriptors will be reflected INSIDE the grid
     *
     * @return Collection|Core\Descriptor[]
     */
    public function getAllDescriptors()
    {
        $c = new GenericCollection(Core\Descriptor::class);

        foreach ($this->levels as $level) {
            $c = $c->merge($level->getAllDescriptors());
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
        $c = MarkMapper::getInstance()->createCollection();

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
    protected function createORMMark(Core\Descriptor $descriptor, Alaress_Date_Format $timestamp, User $student)
    {
        if (!$this->markCache->contains($descriptor)) {
            $this->markCache->attach($descriptor,
                MarkMapper::getInstance()
                 ->createModel()
                 ->setDescriptor($this->createORMDescriptor($descriptor, $descriptor->getLevel(), 0))
                 ->setValid(true)
            );
        }

        return $this->markCache->offsetGet($descriptor)
                               ->setCreatedAt($timestamp)
                               ->setStudent($student);
    }


    /**
     * Return the Twig representation of the data structure this rubric contains
     *
     * @param User $sessionUser|null
     * @return array
     */
    public function toContext(User $sessionUser = null)
    {
        $context = parent::toContext();

        $box = $this->getSubmissionBox();

        // if the box doesn't mark by group, then we need to remove the group subtotals from the context
        foreach ($context['groups'] as $groupId => $group) {

            // if no user specified, OR the specified user is allowed to view
            if (!$sessionUser || $box->canViewRubric($sessionUser)) {

                // set the human readable mark
                if ($box && $box->isMarkedPerRubricGroup()) {
                    $context['groups'][$groupId]['humanReadableMark'] = $this->calculateHumanReadable(
                        $context['groups'][$groupId]['percentage'],
                        $context['groups'][$groupId]['total']
                    );
                } else {
                    // nothing per-group? unset the mark data if any exists
                    unset(
                        $context['groups'][$groupId]['sum'],
                        $context['groups'][$groupId]['total'],
                        $context['groups'][$groupId]['percentage']
                    );
                }
            } else {
                // nothing per-group? unset the mark data if any exists
                unset(
                    $context['groups'][$groupId]['sum'],
                    $context['groups'][$groupId]['total'],
                    $context['groups'][$groupId]['percentage']
                );
            }

        }

        if (!$box || ($sessionUser && !$box->canViewRubric($sessionUser))) {
            unset(
                $context['sum'],
                $context['total'],
                $context['percentage'],
                $context['humanReadableMark']
            );
        }

        $context['boxId'] = $this->box ? $this->box->getId() : 0;

        return $context;
    }


    /**
     * Return the POST-data representation of the data structure this rubric contains
     * This should be the exact inverse of Factory::buildFromPostData()
     *
     * @return array
     */
    public function toPostData()
    {
        $post = [
            'level'      => [],
            'group'      => [],
            'criterion'  => [],
            'descriptor' => []
        ];

        foreach ($this->levels as $levelSequence => $level) {
            $post['level'][$levelSequence] = [
                'id' => $level->getId(),
                'name' => $level->getName()
            ];
        }

        foreach ($this->criteriaGroups as $group) {
            $post['group'][$group->getId()] = [
                'name' => $group->getName()
            ];

            foreach ($group->getAllCriteria() as $criterion) {

                $descriptors = $criterion->getAllDescriptors();

                $post['criterion'][$criterion->getId()] = [
                    'groupId'  => $group->getId(),
                    'on'       => $descriptors->count() > 0,
                    'name'     => $criterion->getName(),
                    'readonly' => $criterion->isReadOnly()
                ];

                foreach ($descriptors as $dCrit) {
                    foreach ($this->levels as $levelSequence => $level) {
                        foreach ($level->getAllDescriptors() as $dLevel) {

                            if ($dCrit == $dLevel) {
                                $post['descriptor'][$criterion->getId()][$levelSequence] = [
                                    'id'     => $dCrit->getId(),
                                    'text'   => $dCrit->getText(),
                                    'points' => $dCrit->getValue()
                                ];
                            }

                        }
                    }
                }
            }
        }

        return $post;
    }


    /**
     * @param float $percentage
     * @param int $total
     * @return string
     */
    protected function calculateHumanReadable($percentage, $total)
    {
        // ugh. handlers...
        if (!$this->box) {
            return '';
        }

        $markType = $this->box->getMarkType();

        if ($markType === null) {
            $markType = MarkTypeMapper::getInstance()->createModel(MarkType::SLUG_PERCENT);
        }

        $marksheet = \Marksheet_Factory::build($markType->getSlug(), $this->box);
        $value = $marksheet->convertRawMark($percentage * 100, $total, true, false);

        return $value;
    }


    /**
     * @return string
     */
    public function getHumanReadableMark()
    {
        return $this->calculateHumanReadable($this->getPercentage(), $this->getTotal());
    }


}
