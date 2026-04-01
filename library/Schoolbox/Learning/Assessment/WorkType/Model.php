<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\WorkType;

use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

class Model implements ISoftDeletable, Sequential
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\CreateCollection;
    use ModelTrait\HasSequence;

    /**
     * The value of lu_work_type.name for the special 'None' row.
     * The 'None' row exists because assessment.work_type_id used to be nullable, but now is not.
     * Assessments that previously had a work_type_id of NULL were given the ID of the special 'None' lu_work_type row.
     * Validation rules ensure that all new assessments are given a non-None work type.
     */
    public const NONE = 'None';

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $externalResultType;

    /**
     * @var boolean
     */
    private $showOnGradesPage;

    /** @var ?bool */
    private ?bool $isVisibleToParents = null;

    /** @var ?bool */
    private ?bool $isVisibleToStudents = null;

    /** @var ?bool */
    private ?bool $permittedProject = null;

    /** @var ?bool */
    private ?bool $permittedDueWork = null;

    /** @var ?bool */
    private ?bool $permittedTask = null;

    /** @var ?bool */
    private ?bool $permittedQuiz = null;

    /** @var ?bool */
    private ?bool $permittedLti = null;

    /** @var ?bool */
    private ?bool $permittedLessonPlan = null;

    /** @var ?bool */
    private ?bool $permittedDialogAgent = null;


    /**
     * @return bool
     */
    public function getShowOnGradesPage()
    {
        return $this->isShowOnGradesPage();
    }


    /**
     * @return bool
     */
    public function isShowOnGradesPage()
    {
        return $this->showOnGradesPage;
    }


    /**
     * @param bool $showOnGradesPage
     */
    public function setShowOnGradesPage(bool $showOnGradesPage): void
    {
        $this->showOnGradesPage = $showOnGradesPage;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $externalResultType
     * @return static
     */
    public function setExternalResultType($externalResultType)
    {
        $this->externalResultType = $externalResultType;
        return $this;
    }


    /**
     * @return string
     */
    public function getExternalResultType()
    {
        return $this->externalResultType;
    }


    /**
     * Human friendly getter
     *
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isStaffOrInstructorVisibleOnly(): bool
    {
        return !$this->isVisibleToParents && !$this->isVisibleToStudents;
    }


    /**
     * @return ?bool
     */
    public function getStaffOrInstructorVisibleOnly(): ?bool
    {
        return $this->isStaffOrInstructorVisibleOnly();
    }


    /**
     * @param bool $staffOrInstructorVisibleOnly
     * @return $this
     */
    public function setStaffOrInstructorVisibleOnly(bool $staffOrInstructorVisibleOnly): self
    {
        if ($staffOrInstructorVisibleOnly) {
            $this->isVisibleToParents = false;
            $this->isVisibleToStudents = false;
        } else {
            $this->isVisibleToParents = true;
            $this->isVisibleToStudents = true;
        }
        return $this;
    }

    /**
     * Human friendly getter
     * @return bool
     */
    public function isVisibleToParents(): bool
    {
        if (null === $this->isVisibleToParents) {
            throw new InsufficientHydrationException('Cannot determine parent visibility from NULL property');
        }
        return $this->isVisibleToParents;
    }

    /**
     * @return ?bool
     */
    public function getIsVisibleToParents(): ?bool
    {
        return $this->isVisibleToParents;
    }

    /**
     * @param bool $isVisibleToParents
     * @return self
     */
    public function setIsVisibleToParents(bool $isVisibleToParents): self
    {
        $this->isVisibleToParents = $isVisibleToParents;
        return $this;
    }

    /**
     * Human friendly getter
     * @return bool
     */
    public function isVisibleToStudents(): bool
    {
        if (null === $this->isVisibleToStudents) {
            throw new InsufficientHydrationException('Cannot determine student visibility from NULL property');
        }
        return $this->isVisibleToStudents;
    }

    /**
     * @return ?bool
     */
    public function getIsVisibleToStudents(): ?bool
    {
        return $this->isVisibleToStudents;
    }

    /**
     * @param bool $isVisibleToStudents
     * @return self
     */
    public function setIsVisibleToStudents(bool $isVisibleToStudents): self
    {
        $this->isVisibleToStudents = $isVisibleToStudents;
        return $this;
    }

    /**
     * Return the visibility of the work type as a string constant, derived from the visibility booleans.
     * @throws InsufficientHydrationException
     * @return string
     */
    public function getVisibility(): string
    {
        if ($this->isVisibleToParents === null || $this->isVisibleToStudents === null) {
            // If not for this exception, this function would return a non-null value even when the underlying data
            // items have not been hydrated.  Avoid misleading the caller by throwing an exception.
            throw new InsufficientHydrationException('Cannot determine visibility from NULL property');
        }

        return match ([$this->isVisibleToParents, $this->isVisibleToStudents]) {
            [false, false] => Mapper::VISIBILITY_STAFF_INSTRUCTORS,
            [false, true ] => Mapper::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS,
            [true , false] => Mapper::VISIBILITY_STAFF_INSTRUCTORS_PARENTS,
            [true , true ] => Mapper::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS_PARENTS,
        };
    }

    /**
     * Set the visibility booleans given a string constant.
     * @param string $visibility
     * @throws \UnexpectedValueException
     * @return self
     */
    public function setVisibility(string $visibility): self
    {
        switch ($visibility) {
            case Mapper::VISIBILITY_STAFF_INSTRUCTORS:
                $this->isVisibleToStudents = false;
                $this->isVisibleToParents = false;
                break;
            case Mapper::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS:
                $this->isVisibleToStudents = true;
                $this->isVisibleToParents = false;
                break;
            case Mapper::VISIBILITY_STAFF_INSTRUCTORS_PARENTS:
                $this->isVisibleToStudents = false;
                $this->isVisibleToParents = true;
                break;
            case Mapper::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS_PARENTS:
                $this->isVisibleToStudents = true;
                $this->isVisibleToParents = true;
                break;
            default:
                throw new \UnexpectedValueException('Unknown visibility: ' . $visibility);
        }
        return $this;
    }

    /**
     * @return ?bool
     */
    public function getPermittedProject(): ?bool
    {
        return $this->permittedProject;
    }

    /**
     * @param bool $permittedProject
     * @return void
     */
    public function setPermittedProject(bool $permittedProject): void
    {
        $this->permittedProject = $permittedProject;
    }

    /**
     * @return ?bool
     */
    public function getPermittedDueWork(): ?bool
    {
        return $this->permittedDueWork;
    }

    /**
     * @param bool $permittedDueWork
     * @return void
     */
    public function setPermittedDueWork(bool $permittedDueWork): void
    {
        $this->permittedDueWork = $permittedDueWork;
    }

    /**
     * @return ?bool
     */
    public function getPermittedTask(): ?bool
    {
        return $this->permittedTask;
    }

    /**
     * @param bool $permittedTask
     * @return void
     */
    public function setPermittedTask(bool $permittedTask): void
    {
        $this->permittedTask = $permittedTask;
    }

    /**
     * @return ?bool
     */
    public function getPermittedQuiz(): ?bool
    {
        return $this->permittedQuiz;
    }

    /**
     * @param bool $permittedQuiz
     * @return void
     */
    public function setPermittedQuiz(bool $permittedQuiz): void
    {
        $this->permittedQuiz = $permittedQuiz;
    }

    /**
     * @return ?bool
     */
    public function getPermittedLti(): ?bool
    {
        return $this->permittedLti;
    }

    /**
     * @param bool $permittedLti
     * @return void
     */
    public function setPermittedLti(bool $permittedLti): void
    {
        $this->permittedLti = $permittedLti;
    }

    /**
     * @return ?bool
     */
    public function getPermittedLessonPlan(): ?bool
    {
        return $this->permittedLessonPlan;
    }

    /**
     * @param bool $permittedLessonPlan
     * @return void
     */
    public function setPermittedLessonPlan(bool $permittedLessonPlan): void
    {
        $this->permittedLessonPlan = $permittedLessonPlan;
    }

    /**
     * @return ?bool
     */
    public function getPermittedDialogAgent(): ?bool
    {
        return $this->permittedDialogAgent;
    }

    /**
     * @param bool $permittedDialogAgent
     * @return void
     */
    public function setPermittedDialogAgent(bool $permittedDialogAgent): void
    {
        $this->permittedDialogAgent = $permittedDialogAgent;
    }
}
