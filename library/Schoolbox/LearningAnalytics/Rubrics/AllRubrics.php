<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LearningAnalytics\Rubrics;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;

class AllRubrics
{
    /**
     * Constructor.
     *
     * @param \Schoolbox\Assessment\Rubric\DataModel\DataSource $rubricMapper
     */
    public function __construct(private \Schoolbox\Assessment\Rubric\DataModel\DataSource $rubricMapper)
    {
    }


    /**
     * Find a set of John Turkey box plot datasets for each capability and indicator.
     *
     * @param GenericCollection<UnitImplementation\Model> $unitImplementations
     * @param array $assessments
     * @param array $overallMarks
     * @return array
     */
    public function generate(
        GenericCollection $unitImplementations,
        array $assessments,
        array $overallMarks
    ): array {
        /* Collect all the caps/indicators from all the homepages used by $unitImplementations
         * This creates a superset of rubric pieces that should encompass setupStruct(), theoretically
         *
         * This is a mega dupe of setupUsedCapabilitiesAndIndicators() internal workings, albeit slightly modified
         */
        $manifest = $this->setupAllCapabilitiesAndIndicators($unitImplementations);

        /* Set up the structure of this panel. Each capability/indicator is sorted alphabetically.
         * Actual statistical values are appended and calculated after populating into $manifest
         *
         * This sounds awful, but remember this is a direct hash lookup implementation. You don't actually have to
         * traverse anything extraordinarily absurd.
         *
         * Still, there might be a way you can roll this into setupStruct() but it'll make it harder to understand;
         * doing so merges the responsibilities
         */
        $manifest = $this->setupUsedCapabilitiesAndIndicators($manifest, $assessments);

        /* Chuck student values in
         */
        $manifest = $this->populateVals($manifest, $overallMarks);

        /* Calculate boxplot data
         */
        $manifest = $this->calculateStats($manifest);

        return $manifest;
    }


    /**
     * Find all the capabilities and indicators used in the folders related to $unitImplementations.
     * This should be the superset of all capabilities and indicators used therein, but I can't be totally sure.
     *
     * @param GenericCollection $unitImplementations
     * @return array
     */
    private function setupAllCapabilitiesAndIndicators(GenericCollection $unitImplementations): array
    {
        $folderIds = array_unique($unitImplementations->map(function (UnitImplementation\Model $unitImpl) {
            return $unitImpl->getFolder()->getId();
        })->getAll());

        $results = $this->rubricMapper->getAvailableIndicators($folderIds);

        $manifest = [];

        foreach ($results as $result) {
            /* This is going to overlap setupStruct() which derives caps/inds from assessments' grids.
             * I suspect that to be a subset of this, so potentially dedupe it.
             *
             * At the moment though I can't be totally sure about the data in the wild.
             */
            $capabilityIndex = $result['grouping'];

            if (!isset($manifest[$capabilityIndex])) {
                $manifest[$capabilityIndex] = [
                    'name' => $result['grouping'],
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

            $indicatorIndex = $result['name'];

            if (!isset($manifest[$capabilityIndex]['indicators'][$indicatorIndex])) {
                $manifest[$capabilityIndex]['indicators'][$indicatorIndex] = [
                    /* Note that capability name is prepended to the indicator name for this particular usage
                     */
                    'name' => sprintf(
                        '%s: %s',
                        $result['grouping'],
                        $result['name'],
                    ),
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

        return $manifest;
    }


    /**
     * Collect all the capabilities and indicators from all the assessments and compile them into a nice list
     * We'll append John Turkey boxplot business later in this method
     *
     * @param array $manifest
     * @param array $assessments
     * @return array
     */
    private function setupUsedCapabilitiesAndIndicators(
        array $manifest,
        array $assessments
    ): array {
        foreach ($assessments as $assessmentId => $assessmentData) {
            foreach ($assessmentData['gridData'] as $rubricInstanceId => $rubricInstanceData) {
                foreach ($rubricInstanceData['capabilities'] as $capabilityId => $capabilityData) {
                    /* This changes whether you wanted to index by capability id or by capability name, depending on
                     * how you wanted to group similarly-named capability names and indicator names.
                     *
                     * I found that two capabilities with the same name, and same indicator names, all had different
                     * identifiers: I'm not entirely sure how that came about, but it seems prevalent in this cohort
                     * data. If they're the same capability name and same indicator name, I guess we *assumed* they
                     * conceptually mean the same thing.
                     *
                     * If you really wanted to segregate this data, index by $capabilityId instead of $capabilityData['name']
                     */
                    $capabilityIndex = $capabilityData['name'];

                    if (!isset($manifest[$capabilityIndex])) {
                        $manifest[$capabilityIndex] = [
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
                        $indicatorIndex = $indicatorData['name'];

                        if (!isset($manifest[$capabilityIndex]['indicators'][$indicatorIndex])) {
                            $manifest[$capabilityIndex]['indicators'][$indicatorIndex] = [
                                /* Note that capability name is prepended to the indicator name for this particular usage
                                 */
                                'name' => sprintf(
                                    '%s: %s',
                                    $capabilityData['name'],
                                    $indicatorData['name'],
                                ),
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
            $capabilityIndex = $capabilityData['name'];

            /* The manifest is a superset built from the folders' indicators and the assessments' grids, so every
             * capability/indicator here should already exist in it. Guard against any mismatch anyway: writing to a
             * missing key would auto-vivify a malformed entry (no name/draft/indicators) that later fatals in
             * calculateStats() and sortByName().
             */
            if (!isset($manifest[$capabilityIndex])) {
                continue;
            }

            foreach ($capabilityData['students'] as $studentId => $studentData) {
                /* Only care about non-null values
                 */
                if (null !== $studentData['published']['normalisedMark']) {
                    $manifest[$capabilityIndex]['published']['vals'][] = $studentData['published']['normalisedMark'];
                }

                if (null !== $studentData['draft']['normalisedMark']) {
                    $manifest[$capabilityIndex]['draft']['vals'][] = $studentData['draft']['normalisedMark'];
                }
            }

            foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                $indicatorIndex = $indicatorData['name'];

                if (!isset($manifest[$capabilityIndex]['indicators'][$indicatorIndex])) {
                    continue;
                }

                foreach ($indicatorData['students'] as $studentId => $studentData) {
                    /* Only care about non-null values
                     */
                    if (null !== $studentData['published']['normalisedMark']) {
                        $manifest[$capabilityIndex]['indicators'][$indicatorIndex]['published']['vals'][] = $studentData['published']['normalisedMark'];
                    }

                    if (null !== $studentData['draft']['normalisedMark']) {
                        $manifest[$capabilityIndex]['indicators'][$indicatorIndex]['draft']['vals'][] = $studentData['draft']['normalisedMark'];
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
}
