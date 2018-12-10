<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;

/**
 * Common logic for Report components
 */
class BaseComponent
{

    /**
     * Iterates through a unit to return all AAS assessments
     *
     * @param UnitImpl\Model $unitImpl
     * @param int|null       $workTypeId
     * @return array
     */
    protected function findAssessmentsWithWorkType(UnitImpl\Model $unitImpl, ?int $workTypeId): array
    {
        $assessables = [];
        foreach ($unitImpl->getAssessables() as $assessable) {
            if ($assessable instanceof Project) {
                foreach ($assessable->getAssessables() as $subAssessable) {
                    if ($this->isWorkType($subAssessable, $workTypeId)) {
                        $assessables[$subAssessable->getId()] = $subAssessable;
                    }
                }
            } elseif ($this->isWorkType($assessable, $workTypeId)) {
                $assessables[$assessable->getId()] = $assessable;
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
}
