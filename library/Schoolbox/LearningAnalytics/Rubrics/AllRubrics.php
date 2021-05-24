<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LearningAnalytics\Rubrics;

use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;

class AllRubrics
{

    /**
     * An array of rubric data to plot
     *
     * @var array
     */
    private $allRubrics;


    /**
     * Consistently build an array key made up of a criterion's grouping + name
     *
     * Does not normalise for comparison.
     *
     * @param string $grouping
     * @param string $name
     * @return string
     */
    private function buildCriterionArrayKey($grouping, $name)
    {
        return "{$grouping}: {$name}";
    }


    /**
     * @param GenericCollection|UnitImplementation\Model[] $unitImplementations
     * @param Term\Model                                   $term
     */
    public function __construct(GenericCollection $unitImplementations, Term\Model $term)
    {
        $rubricMapper = RubricDataSource::getInstance();

        $folderIds = $unitImplementations->map(function (UnitImplementation\Model $unitImpl) {
            return $unitImpl->getFolder()->getId();
        })->getAll();
        $folderIds = array_unique($folderIds);

        // What are the actual due works within each unit implementation?
        $unitImplAssessmentIds = [];
        foreach ($unitImplementations as $unitImplementation) {
            foreach ($unitImplementation->getAssessables() as $assessment) {
                $unitImplAssessmentIds[$assessment->getId()] = true;
            }
        }

        $studentRubricMarks = $rubricMapper->getStudentMarksByIndicatorInFolder($folderIds, $term);
        $allCriteria        = $rubricMapper->getAvailableIndicators($folderIds);

        // Aggregate criteria by grouping+name
        $rubricData = [];
        foreach ($allCriteria as $criteria) {
            $key              = $this->buildCriterionArrayKey($criteria['grouping'], $criteria['name']);
            $rubricData[$key] = [];
        }

        // Aggregate student results per criteria
        /** @var float[][][] $boxData */
        foreach ($studentRubricMarks as $assessmentId => $boxData) {
            if (empty($unitImplAssessmentIds[$assessmentId])) {
                continue;
            }

            foreach ($boxData as $criterionId => $criteria) {
                $key = $this->buildCriterionArrayKey($criteria['grouping'], $criteria['name']);
                foreach ($criteria['marks'] as $studentId => $mark) {
                    if (!is_array($rubricData[$key][$studentId])) {
                        $rubricData[$key][$studentId] = [];
                    }
                    $rubricData[$key][$studentId][] = $mark;
                }
            }
        }

        // Average student marks
        /** @var float[][] $criteria */
        foreach ($rubricData as $criteriaName => $criteria) {
            foreach ($criteria as $studentId => $studentMarks) {
                $rubricData[$criteriaName][$studentId] = array_sum($studentMarks) / count($studentMarks);
            }
        }

        $this->allRubrics = $rubricData;
    }

    /* Getters/Setters
     */

    /**
     * Get all rubrics
     *
     * @return array
     */
    public function getRubricData()
    {
        return $this->allRubrics;
    }
}
