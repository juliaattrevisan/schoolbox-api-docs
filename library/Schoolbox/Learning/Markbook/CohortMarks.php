<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Collection;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\User;

/**
 * Class CohortMarks
 *
 * Generate five-point summaries for assessment cohorts by aggregating
 * feedbacks for all assessments sharing a common assessment specification
 *
 * @package Schoolbox\Learning\Markbook
 */
class CohortMarks
{

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;


    /**
     * @var Collection
     */
    private $assessments;


    /**
     * @var User
     */
    private $viewer;


    /**
     * CohortMarks constructor.
     *
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param Collection            $assessments
     * @param User                  $viewer
     */
    public function __construct(AssessmentMapperProxy $assessmentMapperProxy, Collection $assessments, User $viewer)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->assessments = $assessments;
        $this->viewer = $viewer;
    }


    /**
     * Collate statistics from everyone in the cohort,
     * using $viewee's results as scatter plot values
     *
     * @param User $viewee
     * @return array
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     */
    public function collate(User $viewee): array
    {

        /* A user has participated in each of the elements inside $assessment
         * We need to find all the other assessments that shared a common specification
         *
         * Find all the assessment ids that fit this description
         */
        $assessmentTuples = $this->assessmentMapperProxy->findImplementationIdWithCommonSpecificationId($this->assessments->getPropertyValues('id'));

        /* Use the swamp to hydrate all the participants' pieces via assessmentMapperProxy
         */
        $assessments = $this->assessmentMapperProxy->findAllByIds(array_keys($assessmentTuples));

        /* Populate all the participants' data into this set
         * We need to build the five-point summary using EVERYONE's feedbacks for this assessment specification
         * (normally it's just the guys in the same class, but we're getting a broader data set now)
         */
        $assessments = $this->assessmentMapperProxy
            ->loadParticipantsIntoAssessmentCollection($assessments, null, false, false);

        /* Remember all the boxplot data series grouped by specification id
         */
        $normMarks = [];

        /* For the $viewee, remember their value in a separate data series for highcharts
         */
        $scatter = [];

        /* Remember the unique titles of each $assessment we encountered, for highcharts
         */
        $graphAssessments = [];

        foreach ($assessments as $assessment) {

            /* Obey visibility restrictions; feedback must be visible to $viewer
             * and assessment's mark scheme must be quantitative
             */
            if ($assessment->isFeedbackVisibleToUser($this->viewer) && $assessment->getMarkScheme()->isQuantitative()) {

                /* For each assessment, get all the Participants' feedbacks, and find out their normalised mark
                 * Index them by specification id; we calculate a five point summary of everything here
                 */
                $specificationId = $assessmentTuples[$assessment->getId()];

                /* Remember the unique titles of each $assessment we encountered, for highcharts
                 */
                if (!isset($graphAssessments[$assessment->getTitle()])) {
                    $graphAssessments[$specificationId] = $assessment->getTitle();
                }

                foreach ($assessment->getAllParticipants() as $participant) {
                    $normMarks[$specificationId][] = $participant->getNormalisedMark(true);

                    /* If $participant is the $viewee, remember their result in the $scatter
                     *
                     * TODO What happens when someone does the same assessment implementation in two different places
                     *  They share the same specification, but you get two different results
                     */
                    if ($participant->getUser()->getId() === $viewee->getId()) {
                        $scatter[$specificationId] = $participant->getNormalisedMark(true);
                    }
                }
            }
        }

        /* Now process summaries for each of the data sets we obtained
         */
        $statistics = [];

        foreach ($normMarks as $specificationId => $dataSet) {

            /* TODO: Find out what this means
             */
            $statistics['graphInfo'][] = [];

            /* Yank the name for this specification id
             */
            $statistics['graphAssessments'][] = $graphAssessments[$specificationId];

            /* Calculate a five-point summary for each assessment spec, consisting of all the grades
             * given to all the participants
             */
            $fivePoints = (new Summary($dataSet))->getSummary();

            /* For some reason, use this array struct
             */
            $statistics['graphData'][] = [

                // Minimum
                [
                    'info'  => null,
                    'uid'   => null,
                    'value' => $fivePoints['min'],
                ],
                // 1st Quartile
                [
                    'info'  => null,
                    'uid'   => null,
                    'value' => $fivePoints['quartile1'],
                ],
                // Median
                [
                    'info'  => null,
                    'uid'   => null,
                    'value' => $fivePoints['quartile2'],
                ],
                // 3rd Quartile
                [
                    'info'  => null,
                    'uid'   => null,
                    'value' => $fivePoints['quartile3'],
                ],
                // Maximum
                [
                    'info'  => null,
                    'uid'   => null,
                    'value' => $fivePoints['max'],
                ],
            ];

            /* Yank the scatter for this specification id
             */
            $statistics['graphScatter'][] = $scatter[$specificationId];
        }

        /* Statistics is now a set of everything, but highcharts likes us to encode stuff
         * It's a very fussy eater
         *
         * This is used by Grades/templates/fragments/graph.twig
         */
        return [
            'info' => json_encode($statistics['graphInfo']),
            'assessments' => json_encode($statistics['graphAssessments']),
            'data' => json_encode($statistics['graphData']),
            'scatter' => json_encode($statistics['graphScatter'])
        ];
    }
}
