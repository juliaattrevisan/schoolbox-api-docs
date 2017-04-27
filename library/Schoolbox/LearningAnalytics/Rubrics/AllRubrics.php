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
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\User;

class AllRubrics {

    /**
     * An array of rubric data to plot
     *
     * @var array
     */
    private $allRubrics;


    /**
     * @param GenericCollection|UnitImplementation[] $unitImplementations
     * @param Collection|User[][] $unitImplStudents Grouped by unitImpl.id,
     */
    public function __construct(GenericCollection $unitImplementations, Collection $unitImplStudents)
    {
        $rubricHandler = new DueWork_Rubric_Handler();

        /** @var array[] $indicators */
        $indicators = [];

        /* All the students' values
         */
        $groupedMarks = [];

        /* A list of all the criteria for this folder
         */
        /** @var Folder $folder */
        foreach ($unitImplementations->getColumnValues('folder') as $folder) {
            /** @var User[] $unitStudents */
            foreach ($unitImplStudents as $unitImplementationId => $unitStudents) {
                foreach ($unitStudents as $student) {

                    /* Oddly enough this method gets the students' marks for a
                     * criterion spanning across the entire course, which is
                     * nice and convenient
                     */
                    $markData = $rubricHandler->getStudentMarksByCriteriaInCategory($student->getId(), $folder->getId());

                    foreach ($markData as $indicator) {
                        $groupedMarks[$indicator['name']][] = [
                            'user'  => $student->getId(),
                            'value' => $indicator['value'],
                        ];
                    }
                }
            }

            $indicators = $indicators + $rubricHandler->getAvailableCriteria($folder->getId());
        }

        /* Recombine and preserve criterion grouping
         *
         * You cannot aggregate criteria by their id, because when you
         * spawn rubrics for multiple homepages, criteria get their db rows
         * copied and pasted as entirely new records. They don't have any
         * persistent relations to the parent rubric from which they
         * were copied. That means you have to aggregate based on their
         * name, rather than their id.
         *
         * There is no record of a decision being made about whether a
         * rubric instance is the same as another:
         *
         * + based on their originating id, or
         * + based on their name
         *
         * I suppose one could say that once you change a rubric criterion's
         * name it becomes an entirely new criterion. But then again, that
         * also captures changes that aren't substantial but purely cosmetic
         * like punctuation and other slight changes like that. The names need
         * to be exactly the same in order to aggregate.
         */
        $rubricData = [];

        foreach ($indicators as $criteriaGroup => $currentCriterion) {
            foreach ($currentCriterion as $indicator => $details) {
                $reforged = $details;
                $reforged['allValues'] = $groupedMarks[$reforged['name']];

                /* Group by rubric name, not id
                 */
                if (isset($rubricData[$criteriaGroup][$details['name']])) {
                    $rubricData[$criteriaGroup][$details['name']]['allValues'] = array_merge($rubricData[$criteriaGroup][$details['name']], $reforged['allValues']);
                }
                else {
                    $rubricData[$criteriaGroup][$details['name']] = $reforged;
                }
            }

            /* Sort in order of spawn
             */
            asort($rubricData[$criteriaGroup]);
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
