<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport;

use Custom\Tigs\Summative\StudentAttributes;
use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Common logic for Report components
 */
class BaseComponent
{

    /** @var StudentAttributes */
    private $attributes;

    /**
     * @deprecated Move this logic internal to component
     * @todo Move this logic internal to component
     * @var int $semesterFrom
     */
    protected $semesterFrom = 0;

    /**
     * @deprecated Move this logic internal to component
     * @todo Move this logic internal to component
     * @var int $semesterTo
     */
    protected $semesterTo = 0;

    /** @var User */
    private $viewer;

    /** @var string|null */
    private $roleTypeOverride;


    /**
     * @param StudentAttributes $attributes
     * @param User              $viewer
     * @param null|string       $roleTypeOverride
     * @throws InvalidArgumentException If role type invalid or not permitted for viewer
     */
    public function __construct(StudentAttributes $attributes, User $viewer, ?string $roleTypeOverride)
    {
        // Validate $roleTypeOverride is valid and permitted
        $role       = $viewer->getRole();
        $escalation = true;
        switch ($roleTypeOverride) {
            case Role::GUEST:
            case null:
                // These are always fine
                $escalation = false;
                break;
            case Role::STAFF:
                // Only SU or staff can simulate staff
                if ($viewer->isSuperuser() || $role->isStaff()) {
                    $escalation = false;
                }
                break;
            case Role::STUDENT:
                // Only SU/staff/student can simulate student
                if ($viewer->isSuperuser() || $role->isStaff() || $role->isStudent()) {
                    $escalation = false;
                }
                break;
            case Role::PARENT:
                // Only SU/staff/student/parent can simulate parent
                if ($viewer->isSuperuser() || $role->isStaff() || $role->isStudent() || $role->isParent()) {
                    $escalation = false;
                }
                break;
            default:
                throw new InvalidArgumentException("Unrecognised role type: '$roleTypeOverride'");
        }
        if ($escalation) {
            throw new InvalidArgumentException("{$role->getType()} not permitted to simulate '$roleTypeOverride'");
        }

        $this->attributes       = $attributes;
        $this->viewer           = $viewer;
        $this->roleTypeOverride = $roleTypeOverride;
    }


    /**
     * @return User
     */
    protected function getViewer(): User
    {
        return $this->viewer;
    }


    /**
     * Returns whether the specified assessment's feedback should be visible for the current viewer and role-type override
     *
     * @param IAssessable $assessable
     * @return bool
     */
    protected function isFeedbackVisibleToUser(IAssessable $assessable): bool
    {
        return $assessable->isFeedbackVisibleToRoleType($this->roleTypeOverride ?? $this->viewer->getRole()->getType());
    }


    /**
     * Returns whether we should display errors
     *
     * @return bool
     */
    protected function showErrors(): bool
    {
        $effectiveRole = $this->roleTypeOverride ?? $this->viewer->getRole()->getType();

        return $effectiveRole === Role::STAFF;
    }


    /**
     * Returns whether an assessable is due within this component's report's window
     *
     * @param IAssessable $assessable
     * @return bool
     */
    protected function isDueWithinReportWindow(IAssessable $assessable): bool
    {
        // If a valid semester is not provided, use semester from attribute.
        $semesters = $this->attributes->getCurriculumSemesters();
        if (!\key_exists($this->semesterFrom, $semesters) || !\key_exists($this->semesterTo, $semesters)) {
            $this->setSemesters($this->attributes->getCurriculumSemester());
        }

        $dueDate      = $assessable->getDueAt();
        $reportStart  = $semesters[$this->semesterFrom]->getStart();
        $reportFinish = $semesters[$this->semesterTo]->getFinish();

        return $dueDate->gte($reportStart) && $dueDate->lte($reportFinish);
    }


    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * Iterates through a unit spec to return all assessments with the specified work type
     *
     * If folder is provided, filters to only assessments that were imported to the specified folder
     *
     * @param UnitSpec\Model    $unitSpec
     * @param Folder\Model|null $folder
     * @param int|null          $workTypeId
     * @return IAssessable[]|GenericCollection
     */
    protected function findAssessmentsForFolderAndWorkType(UnitSpec\Model $unitSpec, ?Folder\Model $folder, ?int $workTypeId): GenericCollection
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $assessables = new GenericCollection(IAssessable::class);
        foreach ($unitSpec->getUnitImplementations() as $unitImpl) {
            if ($folder && (int)$unitImpl->getFolder()->getId() !== (int)$folder->getId()) {
                continue;
            }
            foreach ($unitImpl->getAssessables() as $assessable) {
                if ($assessable instanceof AssessmentContext) {
                    foreach ($assessable->getAssessables() as $subAssessable) {
                        if ($this->isWorkType($subAssessable, $workTypeId)) {
                            $assessables[$subAssessable->getId()] = $subAssessable;
                        }
                    }
                } elseif ($this->isWorkType($assessable, $workTypeId)) {
                    $assessables[$assessable->getId()] = $assessable;
                }
            }
        }
        return $assessables;
    }


    /**
     * Returns whether an assessment is of the specified work type.
     *
     * A NULL $workTypeId semantically represents an assessment with no work type.
     *
     * @param IAssessable $assessable
     * @param null|int    $workTypeId
     * @return bool
     */
    private function isWorkType(IAssessable $assessable, ?int $workTypeId): bool
    {
        if (!$assessable->getWorkType()) {
            return null === $workTypeId;
        }

        return $assessable->getWorkType()->getId() === $workTypeId;
    }


    /**
     * @return StudentAttributes
     */
    protected function getAttributes(): StudentAttributes
    {
        return $this->attributes;
    }


    /**
     * @deprecated Move this logic internal to component
     * @todo Move this logic internal to component
     * Implicitly specify the semester of the report
     * @param int $semesterFrom
     * @param int|null $semesterTo
     */
    public function setSemesters(int $semesterFrom, ?int $semesterTo = null): void
    {
        $this->semesterFrom = $semesterFrom;
        if ($semesterTo) {
            $this->semesterTo = $semesterTo;
        } else {
            $this->semesterTo = $semesterFrom;
        }
    }


}
