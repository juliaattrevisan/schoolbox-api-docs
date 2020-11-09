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
     * @var array
     */
    private $studentStatistics = [];


    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;


    /**
     * CohortMarks constructor.
     *
     * @param Collection $assessments
     */
    public function __construct(Collection $assessments)
    {
    }


    /**
     * Collate statistics from everyone in the cohort,
     * using $viewee's results as scatter plot values
     *
     * @param User $viewee
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    protected function collateDataSeries(User $viewee): array
    {

        /* A user has participated in each of the elements inside $assessment
         * We need to find all the other assessments that shared a common specification
         *
         * Find all the assessment ids that fit this description
         *
        $assessmentIds =
            SELECT DISTINCT asi2.implementation_assessment_id
            FROM assessment_specification_implementation asi1
            INNER JOIN assessment_specification_implementation asi2 ON asi1.specification_assessment_id = asi2.specification_assessment_id
            WHERE asi1.implementation_assessment_id IN ( ... )
        */

        /* Use the swamp to hydrate all the participants' pieces via assessmentMapperProxy
         */
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);

        /* Populate all the participants' data into this set
         * We need to build the five-point summary using EVERYONE's feedbacks for this assessment specification
         * (normally it's just the guys in the same class, but we're getting a broader data set now)
         */
        $assessments = $this->assessmentMapperProxy
            ->loadParticipantsIntoAssessmentCollection($assessments, null, false, false);

        /* Remember all the boxplot data series
         */
        $statistics = [];

        /* For the $viewee, remember their value in a separate data series for highcharts
         */
        $scatter = [];

        foreach ($assessments as $assessment) {

            /* For each assessment, get all the Participants' feedbacks, and find out their normalised mark
             */
            $normMarks = [];

            foreach ($assessment->getAllParticipants() as $participant) {
                $normMarks[] = $participant->getNormalisedMark(true);

                /* If $participant is the $viewee, remember their result in the $scatter
                 */
                if ($participant->getUser()->getId() === $viewee->getId()) {
                    $scatter[] = $participant->getNormalisedMark(true);
                }
            }

            /* Calculate a five-point summary for each assessment spec, consisting of all the grades
             * given to all the participants
             */
            $fivePoints = (new Summary($normMarks))->getSummary();

            /* Index by specification id
             */
            $statistics[$assessment->getSpecificationAssessment()->getId()] = [
                'data' => [

                    /* For some reason, use this array struct
                     */

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
                ],
                'scatter' => $scatter
            ];
        }

        return $statistics;
    }


    /**
     * @return array
     */
    public function getStudentStatistics(): array
    {
        return $this->studentStatistics;
    }
}
