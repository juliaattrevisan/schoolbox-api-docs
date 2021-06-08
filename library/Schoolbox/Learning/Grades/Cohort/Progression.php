<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Cohort;

use Alaress\Database\Connector;
use Alaress\Database\Predicate\Builder;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
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
     * We're making raw sql queries here, so I need this thing to do it
     *
     * @var Connector\Base
     */
    protected $database;


    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;


    /**
     * @var GenericCollection
     */
    private $assessments;


    /**
     * @var bool
     */
    private $includeComparison;


    /**
     * @var array
     */
    private $tuples;


    /**
     * Progression constructor.
     *
     * @param Connector\Base        $database
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param GenericCollection     $assessments
     * @param bool                  $includeComparison
     */
    public function __construct(
        Connector\Base $database,
        AssessmentMapperProxy $assessmentMapperProxy,
        GenericCollection $assessments,
        bool $includeComparison
    ) {
        $this->database = $database;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->assessments = $assessments->indexBy('id');
        $this->includeComparison = $includeComparison;
    }


    /**
     * Collect all the five point summary data for each indicator
     *
     * @param User $viewee
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \RangeException
     * @throws \UnderflowException
     */
    public function collate(User $viewee): array
    {

        /* If there are no assessments to examine, exit early, I guess
         * TODO But would you still want to see boxplots for the entire cohort, even so?
         */
        if (!$this->assessments->count()) {
            return [];
        }

        /* Remember this for later; we use it to find data sets by specification id,
         * using the assessment's implementation id as the key
         */
        $this->tuples = $this->getCohortAssessmentSet();

        /* Get the set of assessments whose feedbacks contain rubrics we want to analyse
         * for $viewee's data series
         */
        $vieweeFeedbacks = $this->getVieweeFeedbacks($viewee);

        /* No feedbacks? Exit early, again
         * TODO But would you still want to see boxplots for the entire cohort, even so?
         */
        if (!\count($vieweeFeedbacks)) {
            return [];
        }

        /* Build a list of assessments in a particular order to display in the frontend
         * We'll need to emulate this order when we collect the Five Point summaries
         */

        /* Build a list of capability, indicator, Five Point Summary structs for each of
         * the capabilities and indicators that we found via feedbacks. The overall final
         * structure looks like this:
         */
        //    $longitude = [
        //        $capabilityId => [
        //            'name' =>
        //            'value' =>
        //            'mark_type' => [
        //                $assessmentId =>
        //            ],
        //            'max_value' => [
        //                $assessmentId =>
        //            ],
        //            'mark' => [
        //                $assessmentId =>
        //            ],
        //            'indicators' => [
        //                $indicatorId => [
        //                    'activities' => [
        //                        $assessmentId => [
        //                            'name' =>
        //                            'statistics' => [
        //                                'min' =>
        //                                'q1' =>
        //                                'q2' =>
        //                                'q3' =>
        //                                'max' =>
        //                            ]
        //                        ],
        //                    ]
        //                ]
        //            ]
        //        ],
        //        'assessments' => [
        //            $assessmentId => [
        //                'name' =>
        //                'rubric_capability_mark_type_id' => // obtained, but never used?
        //            ]
        //        ]
        //    ];
        $longitude = $this->setupVieweeSeries($vieweeFeedbacks);

        /* Not going to compare it against the cohort; exit early
         */
        if (!$this->includeComparison) {
            return $longitude;
        }

        /* So I guess you could find all the capabilities for $this->assessments,
         * set up the basic $longitude struct as above,
         * then fill in the five point summary using the cohort set corresponding to $this->assessments
         */
        $longitude = $this->setupCohortSummaries($longitude);

        return $longitude;
    }


    /**
     * Find all the assessment implementation ids which share a common assessment specification
     * with the implementations in $this->assessments
     *
     * @return array
     * @throws \PDOException
     */
    private function getCohortAssessmentSet(): array
    {
        $inputImplementationIds = $this->assessments->getPropertyValues('id');

        /* A user has participated in each of the elements inside $this->assessments
         * We need to find all the other assessments that shared a common specification
         * as the ones in $this->assessments
         */
        $assessmentTuples = $this->assessmentMapperProxy->findImplementationIdWithCommonSpecificationId(
            $inputImplementationIds,
            null,
        );

        /* $assessmentTuples is a set of (key, value) tuples where $key is the implementation id,
         * and $value is a corresponding specification id
         */
        return $assessmentTuples;
    }


    /**
     * Get all the most recent feedbacks for $viewee. This represents the $viewee's data series
     * overlaid on the cohort's boxplots
     *
     * @param User $viewee
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    private function getVieweeFeedbacks(User $viewee): array
    {
        // Find the most recent feedback for each of these due works
        $pb = (new Builder($this->database->getQuoter(), ''))
            ->in('a.id', $this->assessments->getPropertyValues('id'))
            ->isNull('a.deleted_at')
            ->equals('sr.owner', $viewee->getId())
            ->equals('sr.review_route', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->notNull('sr.published_at')
            ->isNull('sr.deleted_at');

        $where = implode(' AND ', $pb->getWhere());

        $feedbackStatement = $this->database->executeQuery("
            SELECT
                sr.id AS feedbackId,
                a.id  AS assessmentId
            FROM submission_return sr
            INNER JOIN submission_box sb ON sr.box_id = sb.id
            INNER JOIN assessment      a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY sr.published_at ASC
        ", $pb->getParams());

        $feedbackIds = [];

        while ($row = $feedbackStatement->fetch(\PDO::FETCH_ASSOC)) {
            $specificationId = $this->tuples[$row['assessmentId']];

            $feedbackIds[$specificationId] = $row['feedbackId'];
        }

        return $feedbackIds;
    }


    /**
     * Set up the longitudinal data struct by first populating it with the $viewee's
     * data series; we'll fill in the cohort level analysis later
     *
     * @param array $feedbackIds
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \RangeException
     * @throws \UnderflowException
     */
    private function setupVieweeSeries(array $feedbackIds): array
    {
        // Find the descriptors selected for each of these feedback IDs
        $pb = new Builder($this->database->getQuoter(), '');
        $pb->in('sr.id', $feedbackIds);

        $where = implode(' AND ', $pb->getWhere());
        $descriptorStatement = $this->database->executeQuery("
            SELECT
                rc.id AS capability_id,
                rc.name AS capability_name,
                rcm.value AS capability_value,
                rcu.max_value AS capability_max_value,
                ri.id AS indicator_id,
                ri.name AS indicator_name,
                a.id AS assessment_id,
                a.title AS assessment_title,
                sb.rubric_capability_mark_type_id,
                sb.mark_type,
                rl.name as level,
                rd.description,
                (SELECT MAX(rd2.max_value) FROM rubric_descriptor rd2 WHERE rd2.rubric_indicator_id = rd.rubric_indicator_id ) as max_value,
                rim.value
            FROM rubric_indicator_mark         rim
            INNER JOIN rubric_capability_mark  rcm ON rim.rubric_capability_mark_id = rcm.id
            INNER JOIN rubric_capability       rc  ON rcm.rubric_capability_id = rc.id
            INNER JOIN rubric_descriptor       rd  ON rim.descriptor_id = rd.id
            INNER JOIN rubric_level            rl  ON rd.level_id = rl.id
            INNER JOIN rubric_indicator        ri  ON rd.rubric_indicator_id = ri.id
            INNER JOIN submission_return       sr  ON rcm.return_id = sr.id
            INNER JOIN submission_box          sb  ON sr.box_id = sb.id
            INNER JOIN rubric_capability_usage rcu ON rc.id = rcu.rubric_capability_id AND rcu.submission_box_id = sb.id
            INNER JOIN assessment              a   ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY a.due_date ASC;
        ", $pb->getParams());

        // Collate the selected descriptor values into longitudinal array
        $longitude = [];
        $assessmentList = [];
        $markSchemeFactory = new MarkSchemeFactory();

        while ($row = $descriptorStatement->fetch(\PDO::FETCH_ASSOC)) {
            $specificationId = $this->tuples[$row['assessment_id']];

            $longitude[$row['capability_id']]['name']                        = $row['capability_name'];
            $longitude[$row['capability_id']]['value'][$specificationId]     = $row['capability_value'];
            $longitude[$row['capability_id']]['mark_type'][$specificationId] = $row['mark_type'];
            $longitude[$row['capability_id']]['max_value'][$specificationId] = $row['capability_max_value'];

            // Create the cap mark
            if (!isset($longitude[$row['capability_id']]['mark'][$specificationId]) && $row['capability_max_value'] > 0 && isset($this->assessments[$row['assessment_id']])) {
                $longitude[$row['capability_id']]['mark'][$specificationId] = $markSchemeFactory->spawnForCapability(
                    $this->assessments[$row['assessment_id']],
                    (float)$row['capability_max_value']
                )->getHumanReadableMark(($row['capability_value'] / $row['capability_max_value']) * 100);
            }

            $longitude[$row['capability_id']]['indicators'][$row['indicator_id']]['name'] = $row['indicator_name'];

            $longitude[$row['capability_id']]['indicators'][$row['indicator_id']]['activities'][$specificationId] = [
                'name' => $row['assessment_title'],
                'rubric_capability_mark_type_id' => $row['rubric_capability_mark_type_id'],
                'descriptor' => $row['description'],
                'level' => $row['level'],
                'max_value' => $row['max_value'],
                'value' => $row['value'],
            ];

            $assessmentList[$specificationId]['name'] = $row['assessment_title'];
            $assessmentList[$specificationId]['rubric_capability_mark_type_id'] = $row['rubric_capability_mark_type_id'];
        }

        $longitude['assessments'] = $assessmentList;

        return $longitude;
    }


    /**
     * Get all the most recent feedbacks for everyone in the cohort.
     *
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    private function getCohortFeedbacks(): array
    {

        // Find the most recent feedback for each of these due works
        $pb = (new Builder($this->database->getQuoter(), ''))
            ->in('a.id', array_keys($this->tuples))
            ->isNull('a.deleted_at')
            ->equals('sr.review_route', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->notNull('sr.published_at')
            ->isNull('sr.deleted_at');

        $where = implode(' AND ', $pb->getWhere());

        $feedbackStatement = $this->database->executeQuery("
            SELECT
                sr.id AS feedbackId,
                a.id  AS assessmentId
            FROM submission_return sr
            INNER JOIN submission_box sb ON sr.box_id = sb.id
            INNER JOIN assessment      a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY sr.published_at ASC
        ", $pb->getParams());

        $feedbackIds = [];

        while ($row = $feedbackStatement->fetch(\PDO::FETCH_ASSOC)) {
            $feedbackIds[] = $row['feedbackId'];
        }

        return $feedbackIds;
    }


    /**
     * Populate the $longitude array struct with Five Point Summaries
     *
     * @param array $longitude
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    private function setupCohortSummaries(array $longitude): array
    {
        $allFeedbackIds = $this->getCohortFeedbacks();

        /* If the cohort has any feedbacks, use them to create a five point summary
         * Otherwise exit early
         */
        if (!\count($allFeedbackIds)) {
            return [];
        }

        /* Start processing five point summaries for all the feedbacks in the cohort
         */
        $allResults = [];

        // Find the descriptors selected for each of these feedback IDs
        $pb = (new Builder($this->database->getQuoter(), ''))
            ->in('sr.id', $allFeedbackIds);

        $where = implode(' AND ', $pb->getWhere());

        $averageStatement = $this->database->executeQuery("
            SELECT
                rc.id AS capability_id,
                ri.id AS indicator_id,
                a.id AS assessment_id,
                a.due_date,
                rim.value
            FROM       rubric_indicator_mark  rim
            INNER JOIN rubric_capability_mark rcm ON rim.rubric_capability_mark_id = rcm.id
            INNER JOIN rubric_capability       rc ON rcm.rubric_capability_id = rc.id
            INNER JOIN rubric_descriptor       rd ON rim.descriptor_id = rd.id
            INNER JOIN rubric_indicator        ri ON rd.rubric_indicator_id = ri.id
            INNER JOIN submission_return       sr ON rcm.return_id = sr.id
            INNER JOIN submission_box          sb ON sr.box_id = sb.id
            INNER JOIN assessment               a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY a.due_date ASC;
        ", $pb->getParams());

        while ($row = $averageStatement->fetch(\PDO::FETCH_ASSOC)) {
            $specificationId = $this->tuples[$row['assessment_id']];

            /* FIXME: Find the common ancestor for $row['capability_id'] instead, so all the indicators' values
             *  are grouped up together for the capability across the cohort...instead of grouped into each
             *  implementation which is what this currently does
             */
            $allResults[$specificationId][$row['capability_id']][$row['indicator_id']][] = (float)$row['value'];
        }

        //Insert the statistical summary data collated from all students results across all indicators in all activities
        foreach ($allResults as $specificationId => $assessment) {
            foreach ($assessment as $capabilityId => $capability) {
                foreach ($capability as $indicatorId => $indicator) {
                    $longitude[$capabilityId]['indicators'][$indicatorId]['activities'][$specificationId]['statistics'] = (new Summary($indicator))->getSummary();
                }
            }
        }

        return $longitude;
    }
}
