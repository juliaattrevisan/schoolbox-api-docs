<?php
/**
 * SelfReflection\Rubric
 *
 * Generates rubric data to display in getParticularResponse()
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\SelfReflection;

use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\DueWork;

class Rubric
{
    /**
     * @var Participant
     */
    private $participant;

    /**
     * @var DueWork\Model
     */
    private $assessment;

    /**
     * @var DueWork\Response\Model
     */
    private $response;

    /**
     * @var DueWork\RubricContext
     */
    private $rubricContext;

    /**
     * Rubric constructor.
     *
     * @param DueWork\Model  $assessment
     * @param Participant    $participant
     * @param null|DueWork\Response\Model $response
     * @param RubricContext  $rubricContext
     */
    public function __construct(
        DueWork\Model $assessment,
        Participant $participant,
        ?DueWork\Response\Model $response,
        RubricContext $rubricContext
    ) {
        $this->assessment = $assessment;
        $this->participant = $participant;
        $this->response = $response;
        $this->rubricContext = $rubricContext;
    }

    /**
     * Generate array pieces for the latest self-reflected feedback and response combination
     *
     * @return array
     */
    public function generate(): array
    {
        $data = [
            'learnerRubricContext' => null,
            'latestSelfReflectedFeedback' => null,
        ];

        /* If there was an IResponse, there might be a self-reflection - try and find it
         * Otherwise just return the defaults (nothing to show/find)
         */
        if ($this->response) {

            /* If this contained a self-reflectable response, load that as well in addition to the usual rubricContext
             * The Instructor needs to see what the Learner chose simultaneously with what the Instructor will give
             *
             * Load a historic self-reflection if one exists - occurs when the Self Reflection state is changed
             * after self-reflections have been created. However, ignore it if the historic self-reflection isn't
             * for the submission currently being viewed
             */
            if ($this->response->isDraft()) {
                $latestSelfReflectedFeedback = $this->response->findMostRecentSelfAssessedFeedback();
            } else {
                $latestSelfReflectedFeedback = $this->response->findMostRecentSelfAssessedPublishedFeedback();
            }

            if ($latestSelfReflectedFeedback) {
                $data['latestSelfReflectedFeedback'] = $latestSelfReflectedFeedback;

                /* Rubric data for the self-reflection
                 * If it was self-reflectable and used a rubric, find out what the latest Learner-owned Feedback is for the
                 * IResponse that we're looking at
                 */
                if ($this->assessment->getUseRubric()) {
                    $data['learnerRubricContext'] = $this->rubricContext->generateMarkableForResponseAndSelfReflection($this->assessment, $this->participant->getUser(), $this->response->getId());
                }
            }
        }

        return $data;
    }
}
