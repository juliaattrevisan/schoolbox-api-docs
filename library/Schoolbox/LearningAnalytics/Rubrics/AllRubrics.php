<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LearningAnalytics\Rubrics;

use Alaress\Database\Connector\IConnector;
use DebugBar\DebugBarException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Markbook\OverallDataset;
use Schoolbox\Assessment\Term;
use Schoolbox\Column\Decimal;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;

class AllRubrics
{
    /**
     * An array of rubric data to plot
     *
     * @var array
     */
    private array $allRubrics;


    /**
     * Constructor.
     */
    public function __construct(private IConnector $databaseInternal)
    {
    }


    /**
     * Find a set of John Turkey box plot datasets for each capability and indicator.
     *
     * @param array $assessments
     * @param array $overallMarks
     * @return array
     */
    public function generate(
        array $assessments,
        array $overallMarks
    ): array {
        /* Set up the structure of this panel. Each capability/indicator is sorted alphabetically.
         * Actual statistical values are appended and calculated after populating into $manifest
         *
         * This sounds awful, but remember this is a direct hash lookup implementation. You don't actually have to
         * traverse anything extraordinarily absurd.
         *
         * Still, there might be a way you can roll this into setupStruct() but it'll make it harder to understand;
         * doing so merges the responsibilities
         */
        $manifest = $this->setupStruct($assessments);

        /* Chuck student values in
         */
        $manifest = $this->populateVals($manifest, $overallMarks);

        /* Calculate boxplot data
         */
        $manifest = $this->calculateStats($manifest);

        return $manifest;
    }


    /**
     * Collect all the capabilities and indicators from all the assessments and compile them into a nice list
     * We'll append John Turkey boxplot business later in this method
     *
     * @param array $assessments
     * @return array
     */
    private function setupStruct(array $assessments): array
    {
        $manifest = [];

        foreach ($assessments as $assessmentId => $assessmentData) {
            foreach ($assessmentData['gridData'] as $rubricInstanceId => $rubricInstanceData) {
                foreach ($rubricInstanceData['capabilities'] as $capabilityId => $capabilityData) {
                    if (!isset($manifest[$capabilityId])) {
                        $manifest[$capabilityId] = [
                            'name' => $capabilityData['name'],
                            /* Capability Marks, if relevant
                             */
                            'published' => [
                                /* Each student's value gets stored here for now
                                 */
                                'vals' => [],
                                /* Boxplot summary eventually gets calculated here from the $vals node once assembled
                                 */
                                'statistics' => null,
                            ],
                            'draft' => [
                                /* Each student's value gets stored here for now
                                 */
                                'vals' => [],
                                /* Boxplot summary eventually gets calculated here from the $vals node once assembled
                                 */
                                'statistics' => null,
                            ],
                            /* Set of indicators in this capability
                             */
                            'indicators' => []
                        ];
                    }

                    foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                        if (!isset($manifest[$capabilityId]['indicators'][$indicatorId])) {
                            $manifest[$capabilityId]['indicators'][$indicatorId] = [
                                'name' => $indicatorData['name'],
                                /* Indicator Marks, if relevant
                                 */
                                'published' => [
                                    /* Each student's value gets stored here for now
                                     */
                                    'vals' => [],
                                    /* Boxplot summary eventually gets calculated here from the $vals node once assembled
                                     */
                                    'statistics' => null,
                                ],
                                'draft' => [
                                    /* Each student's value gets stored here for now
                                     */
                                    'vals' => [],
                                    /* Boxplot summary eventually gets calculated here from the $vals node once assembled
                                     */
                                    'statistics' => null,
                                ],
                            ];
                        }
                    }
                }
            }
        }

        /* Sort the capabilities and indicators alphabetically
         */
        foreach ($manifest as &$capability) {
            uasort($capability['indicators'], [$this, 'sortByName']);
        }

        unset($capability);

        uasort($manifest, [$this, 'sortByName']);

        return $manifest;
    }


    /**
     * Chuck student values into the $manifest via direct hash lookup
     *
     * @param array $manifest
     * @param array $overallMarks
     * @return array
     */
    private function populateVals(array $manifest, array $overallMarks): array
    {
        foreach ($overallMarks as $capabilityId => $capabilityData) {
            foreach ($capabilityData['students'] as $studentId => $studentData) {
                /* Only care about non-null values
                 */
                if (null !== $studentData['published']['normalisedMark']) {
                    $manifest[$capabilityId]['published']['vals'][] = $studentData['published']['normalisedMark'];
                }

                if (null !== $studentData['draft']['normalisedMark']) {
                    $manifest[$capabilityId]['draft']['vals'][] = $studentData['draft']['normalisedMark'];
                }
            }

            foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                foreach ($indicatorData['students'] as $studentId => $studentData) {
                    /* Only care about non-null values
                     */
                    if (null !== $studentData['published']['normalisedMark']) {
                        $manifest[$capabilityId]['indicators'][$indicatorId]['published']['vals'][] = $studentData['published']['normalisedMark'];
                    }

                    if (null !== $studentData['draft']['normalisedMark']) {
                        $manifest[$capabilityId]['indicators'][$indicatorId]['draft']['vals'][] = $studentData['draft']['normalisedMark'];
                    }
                }
            }
        }

        return $manifest;
    }


    /**
     * Calculate John Turkey boxplot summaries in place
     *
     * @param array $manifest
     * @return array
     */
    private function calculateStats(array $manifest): array
    {
        foreach ($manifest as $capabilityId => &$capabilityData) {
            $capabilityData['published']['statistics'] = (new Summary($capabilityData['published']['vals']))->getSummary();
            $capabilityData['draft']['statistics'] = (new Summary($capabilityData['draft']['vals']))->getSummary();

            foreach ($capabilityData['indicators'] as $indicatorId => &$indicatorData) {
                $indicatorData['published']['statistics'] = (new Summary($indicatorData['published']['vals']))->getSummary();
                $indicatorData['draft']['statistics'] = (new Summary($indicatorData['draft']['vals']))->getSummary();
            }
        }

        return $manifest;
    }


    /**
     * Quick alpha sort function for indicators and capabilities summary section
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function sortByName(array $a, array $b): int
    {
        return strnatcasecmp($a['name'], $b['name']);
    }


    /* This used to be in the constructor ????!!!
     * Stashed here for convenient reference
     */
//    private function oldConstructor(GenericCollection $unitImplementations, Term\Model $term) {
        //        $rubricMapper = RubricDataSource::getInstance();
        //
        //        $folderIds = $unitImplementations->map(function (UnitImplementation\Model $unitImpl) {
        //            return $unitImpl->getFolder()->getId();
        //        })->getAll();
        //        $folderIds = array_unique($folderIds);
        //
        //        // What are the actual due works within each unit implementation?
        //        $unitImplAssessmentIds = [];
        //        foreach ($unitImplementations as $unitImplementation) {
        //            foreach ($unitImplementation->getAssessables() as $assessment) {
        //                $unitImplAssessmentIds[$assessment->getId()] = true;
        //            }
        //        }
        //
        //        /* TODO This getStudentMarksByIndicatorInFolder still uses submission return presumptively
        //         *  What is THIS place?
        //         */
        //        $studentRubricMarks = $rubricMapper->getStudentMarksByIndicatorInFolder($folderIds, $term);
        //        $allCriteria        = $rubricMapper->getAvailableIndicators($folderIds);
        //
        //        // Aggregate criteria by grouping+name
        //        $rubricData = [];
        //        foreach ($allCriteria as $criteria) {
        //            $key              = $this->buildCriterionArrayKey($criteria['grouping'], $criteria['name']);
        //            $rubricData[$key] = [];
        //        }
        //
        //        // Aggregate student results per criteria
        //        /** @var float[][][] $boxData */
        //        foreach ($studentRubricMarks as $assessmentId => $boxData) {
        //            if (empty($unitImplAssessmentIds[$assessmentId])) {
        //                continue;
        //            }
        //
        //            foreach ($boxData as $criterionId => $criteria) {
        //                $key = $this->buildCriterionArrayKey($criteria['grouping'], $criteria['name']);
        //                foreach ($criteria['marks'] as $studentId => $mark) {
        //                    if (!is_array($rubricData[$key][$studentId] ?? false)) {
        //                        $rubricData[$key][$studentId] = [];
        //                    }
        //                    $rubricData[$key][$studentId][] = $mark;
        //                }
        //            }
        //        }
        //
        //        // Average student marks
        //        /** @var float[][] $criteria */
        //        foreach ($rubricData as $criteriaName => $criteria) {
        //            foreach ($criteria as $studentId => $studentMarks) {
        //                $rubricData[$criteriaName][$studentId] = array_sum($studentMarks) / count($studentMarks);
        //            }
        //        }
        //
        //        $this->allRubrics = $rubricData;
//    }
//
//    /**
//     * Consistently build an array key made up of a criterion's grouping + name
//     *
//     * Does not normalise for comparison.
//     *
//     * @param string $grouping
//     * @param string $name
//     * @return string
//     */
//    private function buildCriterionArrayKey($grouping, $name)
//    {
//        return "{$grouping}: {$name}";
//    }



    /* Getters/Setters
     */

    /**
     * Get all rubrics
     *
     * @return array
     */
    public function getRubricData(): array
    {
        return $this->allRubrics;
    }
}
