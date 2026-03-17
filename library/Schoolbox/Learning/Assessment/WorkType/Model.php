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

    /** @var bool */
    private $staffOrInstructorVisibleOnly;

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
        if (null === $this->staffOrInstructorVisibleOnly) {
            throw new InsufficientHydrationException('Cannot determine visibility from NULL property');
        }
        return $this->staffOrInstructorVisibleOnly;
    }


    /**
     * Mapper-compatible getter.
     *
     * @return bool
     */
    public function getStaffOrInstructorVisibleOnly(): ?bool
    {
        return $this->staffOrInstructorVisibleOnly;
    }


    /**
     * @param bool $staffOrInstructorVisibleOnly
     * @return $this
     */
    public function setStaffOrInstructorVisibleOnly(bool $staffOrInstructorVisibleOnly): self
    {
        $this->staffOrInstructorVisibleOnly = $staffOrInstructorVisibleOnly;
        return $this;
    }

    /**
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isVisibleToParents(): bool
    {
        if (null === $this->isVisibleToParents) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->isVisibleToParents;
    }

    /**
     * Mapper-compatible getter.
     *
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
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isVisibleToStudents(): bool
    {
        if (null === $this->isVisibleToStudents) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->isVisibleToStudents;
    }

    /**
     * Mapper-compatible getter.
     *
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
     * @return string
     */
    public function getVisibility(): string
    {
        return match (($this->isVisibleToParents() ? 1 : 0) . '-' . ($this->isVisibleToStudents() ? 1 : 0)) {
            '0-0' => Mapper::VISIBILITY_STAFF_ONLY,
            '0-1' => Mapper::VISIBILITY_STAFF_STUDENTS,
            '1-0' => Mapper::VISIBILITY_STAFF_PARENTS,
            '1-1' => Mapper::VISIBILITY_STAFF_STUDENTS_PARENTS,
        };
    }

    /**
     * @param string $visibility
     * @return self
     */
    public function setVisibility(string $visibility): self
    {
        switch ($visibility) {
            case Mapper::VISIBILITY_STAFF_ONLY:
                $this->isVisibleToStudents = false;
                $this->isVisibleToParents = false;
                break;
            case Mapper::VISIBILITY_STAFF_STUDENTS:
                $this->isVisibleToStudents = true;
                $this->isVisibleToParents = false;
                break;
            case Mapper::VISIBILITY_STAFF_PARENTS:
                $this->isVisibleToStudents = false;
                $this->isVisibleToParents = true;
                break;
            case Mapper::VISIBILITY_STAFF_STUDENTS_PARENTS:
                $this->isVisibleToStudents = true;
                $this->isVisibleToParents = true;
                break;
            default:
                throw new \UnexpectedValueException('Unknown visibility: ' . $visibility);
        }
        return $this;
    }

    /**
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isPermittedProject(): bool
    {
        if ($this->permittedProject === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->permittedProject;
    }

    /**
     * Mapper-compatible getter.
     *
     * @return ?bool
     */
    public function getIsPermittedProject(): ?bool
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
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isPermittedDueWork(): bool
    {
        if ($this->permittedDueWork === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->permittedDueWork;
    }

    /**
     * Mapper-compatible getter.
     *
     * @return ?bool
     */
    public function getIsPermittedDueWork(): ?bool
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
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isPermittedTask(): bool
    {
        if ($this->permittedTask === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->permittedTask;
    }

    /**
     * Mapper-compatible getter.
     *
     * @return ?bool
     */
    public function getIsPermittedTask(): ?bool
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
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isPermittedQuiz(): bool
    {
        if ($this->permittedQuiz === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->permittedQuiz;
    }

    /**
     * Mapper-compatible getter.
     *
     * @return ?bool
     */
    public function getIsPermittedQuiz(): ?bool
    {
        if ($this->permittedQuiz === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
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
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isPermittedLti(): bool
    {
        if ($this->permittedLti === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->permittedLti;
    }

    /**
     * Mapper-compatible getter.
     *
     * @return ?bool
     */
    public function getIsPermittedLti(): ?bool
    {
        if ($this->permittedLti === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
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
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isPermittedLessonPlan(): bool
    {
        if ($this->permittedLessonPlan === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->permittedLessonPlan;
    }

    /**
     * Mapper-compatible getter.
     *
     * @return ?bool
     */
    public function getIsPermittedLessonPlan(): ?bool
    {
        if ($this->permittedLessonPlan === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
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
     * Human-friendly getter.
     *
     * @return bool
     */
    public function isPermittedDialogAgent(): bool
    {
        if ($this->permittedDialogAgent === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->permittedDialogAgent;
    }

    /**
     * Mapper-compatible getter.
     *
     * @return ?bool
     */
    public function getIsPermittedDialogAgent(): ?bool
    {
        if ($this->permittedDialogAgent === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
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
