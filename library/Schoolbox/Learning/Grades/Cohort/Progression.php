<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Cohort;

use Alaress\IDatabase;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Class Progression
 *
 * This collates data for the cohort analysis of rubric Capabilities and Indicators
 * It appears in the Progression cab in a student's grades screen
 *
 * @package Schoolbox\Learning\Grades\Cohort
 */
class Progression
{

    /**
     * @var GenericCollection
     */
    private $assessments;


    /**
     * @var bool
     */
    private $includeComparison;


    /**
     * We're making raw sql queries here, so I need this thing to do it
     *
     * @var IDatabase
     */
    protected $database;


    /**
     * Progression constructor.
     *
     * @param IDatabase         $database
     * @param GenericCollection $assessments
     * @param bool              $includeComparison
     */
    public function __construct(IDatabase $database, GenericCollection $assessments, bool $includeComparison)
    {
        $this->database = $database;
        $this->assessments = $assessments;
        $this->includeComparison = $includeComparison;
    }


    /**
     * Collect all the five point summary data for each indicator
     * @param User $viewee
     * @return array
     */
    public function collate(User $viewee): array
    {
        /* Get the set of assessments whose feedbacks contain rubrics we want to analyse
         */

        /* Build a list of assessments in a particular order to display in the frontend
         * We'll need to emulate this order when we collect the Five Point summaries
         */

        /* Build a list of capability, indicator, Five Point Summary structs for each of
         * the capabilities and indicators that we found via feedbacks. The overall final
         * structure looks like this:
         */
//        $longitude = [
//            $capabilityId => [
//                'name' =>
//                'value' =>
//                'mark_type' => [
//                    $assessmentId =>
//                ],
//                'max_value' => [
//                    $assessmentId =>
//                ],
//                'mark' => [
//                    $assessmentId =>
//                ],
//                'indicators' => [
//                    $indicatorId => [
//                        'activities' => [
//                            $assessmentId => [
//                                'name' =>
//                                'statistics' => [
//                                    'min' =>
//                                    'q1' =>
//                                    'q2' =>
//                                    'q3' =>
//                                    'max' =>
//                                ]
//                            ],
//                        ]
//                    ]
//                ]
//            ],
//            'assessments' => [
//                $assessmentId => [
//                    'name' =>
//                    'rubric_capability_mark_type_id' => // obtained, but never used?
//                ]
//            ]
//        ];

        /* So I guess you could find all the capabilities for $this->assessments,
         * set up the basic $longitude struct as above,
         * then fill in the five point summary using the cohort set corresponding to $this->assessments
         */

            /* Decide the value(s) attributable to each of the indicators, via the value
             * of the elected descriptor
             */

        return [];
    }
}
