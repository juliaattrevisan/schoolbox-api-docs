<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Collection;
use Schoolbox\Config\Loader;
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
     * @var bool
     */
    private $gradeGradientEnabled;


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
     * @var array
     */
    private $tuples;


    /**
     * CohortMarks constructor.
     *
     * @param bool                  $gradeGradientEnabled
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param Collection            $assessments
     * @param User                  $viewer
     */
    public function __construct(
        bool $gradeGradientEnabled,
        AssessmentMapperProxy $assessmentMapperProxy,
        Collection $assessments,
        User $viewer
    ) {
        $this->gradeGradientEnabled = $gradeGradientEnabled;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->assessments = $assessments;
        $this->viewer = $viewer;
    }


    /**
     * The assessments (and all their participants) that contain all Feedback values
     * we need to analyse. These assessments consist of all their sibling implementations,
     * which are ones that have the same assessment specification, allowing us to collate
     * data from everyone who did an assessment derived from the same specification
     *
     * I've made a quick sql shortcut that saves you from having to fight with the swamp, hydrating
     * useless data and so on. Instead it gives you a conclusive set of ids which you hydrate via
     * the MapperProxy.
     *
     * The resultant assessments' order is then made the same as the input's order.
     *
     * @return Collection
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function getCohortAssessmentSet(): Collection
    {

        /* The input implementation ids are in a particular sort order which isn't easy to replicate
         * It's nominally by Due Date, but CAP plans throw a spanner in the works as you have to
         * examine those instead of the assessment's due date; to skip this hankypanky I'll just put
         * the result set in the same order as the input set
         */
        $inputImplementationIds = $this->assessments->getPropertyValues('id');

        /* A user has participated in each of the elements inside $this->assessments
         * We need to find all the other assessments that shared a common specification
         * as the ones in $this->assessments
         */
        $assessmentTuples = $this->assessmentMapperProxy->findImplementationIdWithCommonSpecificationId($inputImplementationIds);

        /* Use the swamp to hydrate all the participants' pieces via assessmentMapperProxy
         */
        $assessments = $this->assessmentMapperProxy->findAllByIds(array_keys($assessmentTuples));

        /* Populate all the participants' data into this set
         * We need to build the five-point summary using EVERYONE's feedbacks for this assessment specification
         * (normally it's just the guys in the same class, but we're getting a broader data set now)
         */
        $assessments = $this->assessmentMapperProxy
            ->loadParticipantsIntoAssessmentCollection($assessments, null, false, false)
            ->indexBy('id');

        /* Put specifications into the same order as their input implementations
         *
         * The findAllByIds() can produce a result set in a different order so we have to forcibly
         * return it to its original order. There might be a faster/smarter way to do this but I'll
         * accept O(n^2) complexity on two integer arrays
         */
        $sortedSpecificationIds = [];

        foreach ($inputImplementationIds as $inputImplementationId) {
            foreach ($assessmentTuples as $implementationId => $specificationId) {
                if ($implementationId === $inputImplementationId) {
                    $sortedSpecificationIds[] = $specificationId;
                }
            }
        }

        $sortedTuples = [];

        foreach ($sortedSpecificationIds as $sortedSpecificationId) {
            foreach ($assessmentTuples as $implementationId => $specificationId) {
                if ($specificationId === $sortedSpecificationId) {
                    $sortedTuples[$implementationId] = $sortedSpecificationId;
                }
            }
        }

        /* Remember this for later
         */
        $this->tuples = $sortedTuples;

        /* Index the result set, forcing the $assessments collection to be in the same
         * specification order as $inputImplementationIds (jesus)
         */
        $result = new Collection();

        foreach ($sortedTuples as $implementationId => $specificationId) {
            $result->add($assessments->getOffset($implementationId));
        }

        return $result;
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

        /* Get all the assessments we need to analyse
         */
        $assessments = $this->getCohortAssessmentSet();

        /* Remember all the boxplot data series grouped by specification id
         */
        $normMarks = [];

        /* For the $viewee, remember their value in a separate data series for highcharts
         */
        $scatter = [];

        /* Remember the unique titles of each $assessment we encountered, for highcharts
         */
        $graphAssessments = [];

        /* Remember the $viewee's accomplishment in each specification id
         */
        $graphInfo = [];

        foreach ($assessments as $assessment) {

            /* Obey visibility restrictions; feedback must be visible to $viewer
             * and assessment's mark scheme must be quantitative
             */
            if ($assessment->isFeedbackVisibleToUser($this->viewer) && $assessment->getMarkScheme()->isQuantitative()) {

                /* For each assessment, get all the Participants' feedbacks, and find out their normalised mark
                 * Index them by specification id; we calculate a five point summary of everything here
                 */
                $specificationId = $this->tuples[$assessment->getId()];

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

                        /* Find out what this $viewee accomplished; null normalised marks are disregarded
                         * We optionally apply a gradient colour
                         */
                        $normalisedMark = $participant->getNormalisedMark(true);
                        $scatter[$specificationId] = $normalisedMark;

                        /* This little data set appears in highcharts when you hover over the
                         * $viewee's scatter plot, telling you some more metadata about what they achieved
                         */
                        $comment = null;

                        /* Look for an authoritative feedback to get the most recent info
                         */
                        $feedback = $participant->findAuthoritativeVisibleFeedback($viewee);

                        if ($feedback) {
                            $comment = $feedback->getComment();
                        }

                        /* Get the nicer version of the normalised mark
                         */
                        $humanReadableMark = $participant->getHumanReadableMark(true);

                        /* If eitehr of these two vars are truthy, there's something to show
                         */
                        if ($humanReadableMark || $comment) {
                            $graphInfo[$specificationId] = [
                                'displayMark'  => $humanReadableMark,
                                'gradientMark' => $this->gradeGradientEnabled ? (int)($normalisedMark / 10) : 'none',
                                'info'         => $comment ?? '',
                            ];
                        } else {

                            /* Otherwise, nothing to show
                             */
                            $graphInfo[$specificationId] = [];
                        }
                    }
                }
            }
        }

        /* Now process summaries for each of the data sets we obtained
         */
        $statistics = [];

        foreach ($normMarks as $specificationId => $dataSet) {

            /* The hover info thingy on the highcharts
             */
            $statistics['graphInfo'][] = $graphInfo[$specificationId];

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
