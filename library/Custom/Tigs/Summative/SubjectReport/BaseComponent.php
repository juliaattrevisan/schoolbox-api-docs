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
     * Returns whether an assessable is due within this component's report's (semester or year) window
     *
     * @param IAssessable $assessable
     * @param int|null    $semester   If present, filter to this semester, otherwise filter to year
     * @return bool
     */
    protected function isDueWithinReportWindow(IAssessable $assessable, int $semester = null): bool
    {
        $semesters = $this->attributes->getCurriculumSemesters();

        $startDate = $semester === null
            ? $this->attributes->getCurriculumYearStart()
            : $semesters[$semester]->getStart();

        $finishDate = $semester === null
            ? $this->attributes->getCurriculumYearFinish()
            : $semesters[$semester]->getFinish();

        $dueDate = $assessable->getDueAt();

// Unleash this beast when your NESA Graphs aren't displaying but you expected
// them to display. Sometimes it's because their assessments aren't being found
// in the appropriate semesters, due to this derivation business logic going on
// elsewhere in Report.php
//
//        if (!($dueDate->gte($startDate) && $dueDate->lte($finishDate))) {
//            new \Alaress_Probe(sprintf(
//                "%s is not due in semester %s; checking the semesters array instead of attributes",
//                $assessable->getTitle(),
//                $semester
//            ));
//
//            new \Alaress_Probe(sprintf(
//                "Semester 1: starts %s, ends %s",
//                $semesters[1]->getStart()->getDatabase(),
//                $semesters[1]->getFinish()->getDatabase(),
//            ));
//
//            new \Alaress_Probe(sprintf(
//                "Semester 2: starts %s, ends %s",
//                $semesters[2]->getStart()->getDatabase(),
//                $semesters[2]->getFinish()->getDatabase(),
//            ));
//        }

        return $dueDate->gte($startDate) && $dueDate->lte($finishDate);
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
     * @return string|null
     */
    protected function getRoleTypeOverride(): ?string
    {
        return $this->roleTypeOverride;
    }
}
