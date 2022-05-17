<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Cohort;

use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\User;

/**
 * Class ClassResults
 *
 * Generate five-point summaries for assessment cohorts by aggregating
 * feedbacks for all assessments sharing a common assessment specification
 *
 * @package Schoolbox\Learning\Grades\Cohort
 */
class ClassResults
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
     * The academic period (a.k.a. term) to limit the cohort to.
     */
    private Term $term;


    /**
     * @var array
     */
    private $tuples;


    /**
     * ClassResults constructor.
     *
     * @param bool                  $gradeGradientEnabled
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param Collection            $assessments
     * @param User                  $viewer
     * @param Term                  $term
     */
    public function __construct(
        bool $gradeGradientEnabled,
        AssessmentMapperProxy $assessmentMapperProxy,
        Collection $assessments,
        User $viewer,
        Term $term
    ) {
        $this->gradeGradientEnabled = $gradeGradientEnabled;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->assessments = $assessments;
        $this->viewer = $viewer;
        $this->term = $term;
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
     * @return array
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function getCohortAssessmentSet(): array
    {
        if ($this->assessments->count() === 0) {
            return [];
        }

        /* The input implementation ids are in a particular sort order which isn't easy to replicate
         * It's nominally by Due Date, but CAP plans throw a spanner in the works as you have to
         * examine those instead of the assessment's due date; to skip this hankypanky I'll just put
         * the result set in the same order as the input set
         */
        $inputImplementationIds = $this->assessments->getPropertyValues('id');

        /* A user has participated in each of the elements inside $this->assessments
         * We need to find all the other assessments that shared a common specification
         * as the ones in $this->assessments
         * (Also filter out assessments not due this term)
         */
        $assessmentTuples = $this->assessmentMapperProxy->findImplementationIdWithCommonSpecificationId(
            $inputImplementationIds,
            $this->term,
        );

        /* Find all marks
         */
        $assessmentMarks = $this->assessmentMapperProxy->findNormalisedMark(array_keys($assessmentTuples));

        /* Put specifications into the same order as their input implementations
         *
         * The findAllByIds() can produce a result set in a different order so we have to forcibly
         * return it to its original order. There might be a faster/smarter way to do this but I'll
         * accept O(n^2) complexity on two integer arrays
         */
        $sortedSpecificationIds = [];

        foreach (array_flip($inputImplementationIds) as $inputImplementationId => $i) {
            $sortedSpecificationIds[] = $assessmentTuples[$inputImplementationId] ?? null;
        }

        /* Index the result set, forcing the $assessments collection to be in the same
         * specification order as $inputImplementationIds (jesus)
         */
        $result = [];

        $sortedTuples = [];

        foreach ($sortedSpecificationIds as $sortedSpecificationId) {
            foreach ($assessmentTuples as $implementationId => $specificationId) {
                // Only add this implementation to the result set if it actually
                // is in the list of assessments that are due within this term.
                if ($specificationId === $sortedSpecificationId && isset($assessmentMarks[$implementationId])) {
                    $sortedTuples[$implementationId] = $sortedSpecificationId;

                    /* Stack it up in the same order
                     */
                    $result[$implementationId] = $assessmentMarks[$implementationId];
                }
            }
        }

        /* Remember this for later; we use it to find data sets by specification id,
         * using the assessment's implementation id as the key
         */
        $this->tuples = $sortedTuples;

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
     * @throws \JsonException
     */
    public function collate(User $viewee): array
    {

        /* Get all the assessments we need to analyse
         */
        $assessmentMarks = $this->getCohortAssessmentSet();

        /* Remember all the boxplot data series grouped by specification id
         */
        $normMarks = [];

        /* Highcharts' data format expects each series to be in its own element:
         * (you can't collect them into a struct)
         *
         * so all the assessment titles in an ordered array,
         * all the hover contents are in an ordered array,
         * the $viewee's results are in an ordered array
         */

        /* Remember the unique titles of each $assessment we encountered, for highcharts
         */
        $graphAssessments = [];

        /* Remember the $viewee's accomplishment in each specification id
         */
        $graphInfo = [];

        /* For the $viewee, remember their value in a separate data series for highcharts
         */
        $graphScatter = [];

        /* Get all the cohort's results and pump them into a struct organised by specification id
         * We'll need to make a five-point summary of each of these specifications' values
         */
        foreach ($assessmentMarks as $assessmentId => $marks) {
            /* Assessment's mark scheme must be quantitative
             *
             * There's no check on isFeedbackVisibleToUser because we're doing cohort analysis
             * and the currently logged in user might not be able to see other class' Feedbacks,
             * but those values still contribute to the statistical numbers
             */

            /* For each assessment, get all the Participants' feedbacks, and find out their normalised mark
             * Index them by specification id; we eventually calculate a five point summary of everything here
             */
            $specificationId = $this->tuples[$assessmentId];
            $normMarks[$specificationId] ??= [];
            foreach ($marks as $mark) {
                if ($mark === null) {
                    continue;
                }
                $normMarks[$specificationId][] = $mark;
            }
        }

        /* Collate the $viewee's data, which is their scatter plot and hover thingo
         * This little data set appears in highcharts when you hover over the
         * $viewee's scatter plot, telling you some more metadata about what they achieved
         *
         * The scatter plot represents the $viewee's feedbacks for an assessment for the given class
         *
         * $assessments and $this->assessments are ordered similarly, by same specification id
         * so we can ASSUME something here about the sequence of $assessment
         */
        foreach ($this->assessments as $assessment) {
            if (!key_exists($assessment->getId(), $this->tuples)) {
                // this assessment is not for cohort
                continue;
            }
            $specificationId = $this->tuples[$assessment->getId()];

            /* Remember the unique titles of each $assessment we encountered, for highcharts
             */
            $graphAssessments[$specificationId] ??= $assessment->getTitle();

            /* Obey visibility restrictions; feedback must be visible to $viewer
             * and assessment's mark scheme must be quantitative
             */
            if ($assessment->isFeedbackVisibleToUser($this->viewer) && $assessment->getMarkScheme()->isQuantitative()) {
                $participant = $assessment->getParticipantIfExtant($viewee);

                /* Account for null scenario where this person participated but doesn't exist
                 */
                if (null === $participant) {
                    continue;
                }

                /* Find out what this $viewee accomplished; null normalised marks are disregarded
                 * We optionally apply a gradient colour
                 */
                $normalisedMark = $participant->getNormalisedMark(true);
                $graphScatter[$specificationId] = $normalisedMark;

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

                /* If either of these two vars are truthy, there's something to show
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

        /* Now process summaries for each of the data sets we obtained
         * Each assessment needs its own five-point summary
         */
        $statistics = [
            'graphInfo'         => [],
            'graphAssessments'  => [],
            'graphData'         => [],
            'graphScatter'      => [],
        ];

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
            $statistics['graphScatter'][] = $graphScatter[$specificationId];
        }

        /* Statistics is now a set of everything, but highcharts likes us to encode stuff
         * It's a very fussy eater
         *
         * This is used by Grades/templates/fragments/graph.twig
         */
        return [
            'info' => json_encode($statistics['graphInfo'], JSON_THROW_ON_ERROR),
            'assessments' => json_encode($statistics['graphAssessments'], JSON_THROW_ON_ERROR),
            'data' => json_encode($statistics['graphData'], JSON_THROW_ON_ERROR),
            'scatter' => json_encode($statistics['graphScatter'], JSON_THROW_ON_ERROR),
        ];
    }
}
