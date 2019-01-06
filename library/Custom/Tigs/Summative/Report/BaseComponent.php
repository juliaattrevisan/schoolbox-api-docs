<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;

/**
 * Common logic for Report components
 */
class BaseComponent
{

    /** @var StudentAttributes */
    private $attributes;


    /**
     * @param StudentAttributes $attributes
     */
    public function __construct(StudentAttributes $attributes)
    {
        $this->attributes = $attributes;
    }


    /**
     * Returns whether an assessable is due within this component's report's window
     *
     * @param IAssessable $assessable
     * @return bool
     */
    protected function isDueWithinReportWindow(IAssessable $assessable): bool
    {
        $dueDate      = $assessable->getDueAt();
        $reportStart  = $this->attributes->getReportStart();
        $reportFinish = $this->attributes->getReportFinish();
        return $dueDate->gte($reportStart) && $dueDate->lt($reportFinish);
    }


    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * Iterates through a unit spec to return all AAS assessment implementations that were imported to the specified folder
     *
     * @param UnitSpec\Model $unitSpec
     * @param Folder\Model   $folder
     * @param int|null       $workTypeId
     * @return IAssessable[]|GenericCollection
     */
    protected function findAssessmentsForFolderAndWorkType(UnitSpec\Model $unitSpec, Folder\Model $folder, ?int $workTypeId): GenericCollection
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $assessables = new GenericCollection(IAssessable::class);
        foreach ($unitSpec->getUnitImplementations() as $unitImpl) {
            if ((int)$unitImpl->getFolder()->getId() !== (int)$folder->getId()) {
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

    // Getters


    /**
     * @return StudentAttributes
     */
    protected function getAttributes(): StudentAttributes
    {
        return $this->attributes;
    }
}
