<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Rubrics;

use DueWork_Rubric_Handler;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\User;
use Schoolbox\Assessment\Term;

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
        // TODO attempt to remove reliance on deprecated class
        $rubricHandler = new DueWork_Rubric_Handler();

        $folderIds = $unitImplementations->map(function (UnitImplementation\Model $unitImpl) {
            return $unitImpl->getFolder()->getId();
        })->getAll();
        $folderIds = array_unique($folderIds);

        $studentRubricMarks = $rubricHandler->getStudentMarksByCriteriaInFolder($folderIds, $term);
        $allCriteria        = $rubricHandler->getAvailableCriteria($folderIds);

        // Aggregate criteria by grouping+name
        $rubricData = [];
        foreach ($allCriteria as $criteria) {
            $key              = $this->buildCriterionArrayKey($criteria['grouping'], $criteria['name']);
            $rubricData[$key] = [];
        }

        // Aggregate student results per criteria
        foreach ($studentRubricMarks as $boxId => $boxData) {
            /** @var float[][][] $boxData */
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
        foreach ($rubricData as $criteriaName => $criteria) {
            /** @var float[][] $criteria */
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
