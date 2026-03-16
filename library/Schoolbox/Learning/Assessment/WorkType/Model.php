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

    /** @var bool */
    private ?bool $isVisibleToParents = null;

    /** @var bool */
    private ?bool $isVisibleToStudents = null;

    /** @var ?bool */
    private ?bool $permittedProject = null;
    /** @var ?bool */
    private ?bool $permittedDueWork = null;
    /** @var ?bool */
    private ?bool $permittedTask = null;
    /** @var ?bool */
    private ?bool $permittedQuiz= null;
    /** @var ?bool */
    private ?bool $permittedLti= null;
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
     * @return bool
     */
    public function getIsVisibleToParents(): bool
    {
        if (null === $this->isVisibleToParents) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->isVisibleToParents;
    }

    /**
     * @param bool $isVisibleToParents
     * @return bool
     */
    public function setIsVisibleToParents(bool $isVisibleToParents): bool
    {
        return $this->isVisibleToParents = $isVisibleToParents;
    }

    /**
     * @return bool
     */
    public function getIsVisibleToStudents(): bool
    {
        if (null === $this->isVisibleToStudents) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
        return $this->isVisibleToParents;
    }

    /**
     * @param bool $isVisibleToStudents
     * @return bool
     */
    public function setIsVisibleToStudents(bool $isVisibleToStudents): bool
    {
        return $this->isVisibleToStudents = $isVisibleToStudents;
    }

    /**
     * @return string
     */
    public function getVisibility(): string
    {
        return match (($this->getIsVisibleToParents() ? 1 : 0) . '-' . ($this->getIsVisibleToStudents() ? 1 : 0)) {
            '0-0' => Mapper::VISIBILITY_STAFF_ONLY,
            '0-1' => Mapper::VISIBILITY_STAFF_STUDENTS,
            '1-0' => Mapper::VISIBILITY_STAFF_PARENTS,
            '1-1' => Mapper::VISIBILITY_STAFF_STUDENTS_PARENTS,
            default => throw new \LogicException('Impossible case.'),
        };
    }

    /**
     * @return bool
     */
    public function getPermittedProject(): bool
    {
        if ($this->permittedProject === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
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
     * @return bool
     */
    public function getPermittedDueWork(): bool
    {
        if ($this->permittedDueWork === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
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
     * @return bool
     */
    public function getPermittedTask(): bool
    {
        if ($this->permittedTask === null) {
            throw new InsufficientHydrationException('This WorkType object is not hydrated.');
        }
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
     * @return bool
     */
    public function getPermittedQuiz(): bool
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
     * @return bool
     */
    public function getPermittedLti(): bool
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
     * @return bool
     */
    public function getPermittedLessonPlan(): bool
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
     * @return bool
     */
    public function getPermittedDialogAgent(): bool
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
