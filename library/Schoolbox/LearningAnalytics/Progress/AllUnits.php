<?php
/**
 * AllUnits
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Progress;

use Schoolbox\LearningAnalytics\Registry;

class AllUnits {

    /**
     * An instance of LearningAnalytics\Registry
     *
     * @var Registry
     */
    private $registry = null;

    /**
     * An array containing unitId-(unit,mostAdvancedStudent,classCompletions)
     * relations
     *
     * @var array
     */
    private $stackedUnitProgress = array();

    /**
     * Constructor
     *
     * @param Registry $registry
     */
    public function __construct(Registry $registry) {
        $this->setRegistry($registry);

        $this->process();
    }

    /**
     * Process
     */
    private function process() {
        $allUnitProgress = array();
        $everyone = $this->getRegistry()->getAllStudentRegistry();

        foreach ($this->getRegistry()->getAllUnitRegistry() as $currentUnit) {
            $allStudents = $everyone[$currentUnit->getId()];

            $unitProgress = new Unit($allStudents, $currentUnit);

            $allUnitProgress[] = array(
                'unit' => $currentUnit,
                'mostAdvancedStudent' => $unitProgress->getMostAdvancedStudent(),
                'classCompletions' => $unitProgress->getClassCompletions()
            );
        }

        /* Combine them, averaging stacked results
         * Alternatively to display all the units in their original separated
         * states, just use $allUnitProgress instead of $stackedUnitProgress
         */
        $stackedUnitProgress = array();

        foreach ($allUnitProgress as $currentUnitProgress) {
            $key = $currentUnitProgress['unit']->getUnitCategory()->getId();

            /* Insert new values
             */
            if (!isset($stackedUnitProgress[$key])) {
                $stackedUnitProgress[$key] = $currentUnitProgress;
            }
            /* Average between this set of values and the existing ones for
             * class completion; use greatest value for most advanced student
             */
            else {
                /* Max most advanced student
                 */
                $existingMostAdvancedStudent = $stackedUnitProgress[$key]['mostAdvancedStudent'];
                $otherMostadvancedStudent = $currentUnitProgress['mostAdvancedStudent'];

                $stackedUnitProgress[$key]['mostAdvancedStudent'] = max($existingMostAdvancedStudent, $otherMostadvancedStudent);

                /* Class Completion average
                 */
                $existingClassCompletions = $stackedUnitProgress[$key]['classCompletions'];
                $otherClassCompletions = $currentUnitProgress['classCompletions'];

                $stackedUnitProgress[$key]['classCompletions'] = 0.5 * ($existingClassCompletions + $otherClassCompletions);
            }
        }

        $this->setStackedUnitProgress($stackedUnitProgress);
    }

    /* Getters/Setters
     */

    /**
     * Set registry
     *
     * @param Registry $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get registry
     *
     * @return Registry
     */
    private function getRegistry()
    {
        return $this->registry;
    }

    /**
     * Set stacked unit progress
     *
     * @param array $stackedUnitProgress
     */
    private function setStackedUnitProgress($stackedUnitProgress)
    {
        $this->stackedUnitProgress = $stackedUnitProgress;
    }

    /**
     * Get stacked unit progress
     *
     * @return array
     */
    public function getStackedUnitProgress()
    {
        return $this->stackedUnitProgress;
    }
}