<?php
/**
 * SelfReflection
 *
 * Generates rubric data to display in getParticularResponse()
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\SelfReflection;

use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;


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
     * @var User
     */
    private $currentUser;

    /**
     * @var IResponse
     */
    private $response;

    /**
     * @var bool
     */
    private $isInteractionLocked = false;

    /**
     * @var DueWork\RubricContext
     */
    private $rubricContext;

    /**
     * Rubric constructor.
     *
     * @param DueWork\Model  $assessment
     * @param Participant    $participant
     * @param User           $currentUser
     * @param null|IResponse $response
     * @param bool           $isInteractionLocked
     * @param RubricContext  $rubricContext
     */
    public function __construct(
        DueWork\Model $assessment, Participant $participant, User $currentUser,
        ?IResponse $response, bool $isInteractionLocked, RubricContext $rubricContext)
    {
        $this->assessment = $assessment;
        $this->participant = $participant;
        $this->currentUser = $currentUser;
        $this->response = $response;
        $this->isInteractionLocked = $isInteractionLocked;
        $this->rubricContext = $rubricContext;
    }

    /**
     * Generate array pieces for the latest self-reflected feedback and response combination
     */
    public function generate()
    {
        $data = [
            'learnerRubricContext' => null,
            'latestSelfReflectedFeedback' => null,
        ];

        /* If this contained a self-reflectable response, load that as well in addition to the usual rubricContext
         * The Instructor needs to see what the Learner chose simultaneously with what the Instructor will give
         */
        /** @var IFeedback $latestSelfReflectedFeedback */
        $latestSelfReflectedFeedback = $this->participant->findMostRecentPublishedSelfReflectedFeedback();

        /* Load a historic self-reflection if one exists - occurs when the Self Reflection state is changed
         * after self-reflections have been created. However, ignore it if the historic self-reflection isn't
         * for the submission currently being viewed
         */
        if ($latestSelfReflectedFeedback && $latestSelfReflectedFeedback->getResponse()->getId() === $this->response->getId()) {

            /* Rubric data for the self-reflection
             * If it was self-reflectable and used a rubric, find out what the latest Learner-owned Feedback is for the
             * IResponse that we're looking at
             */
            if ($this->assessment->getUseRubric()) {
                $data['latestSelfReflectedFeedback'] = $latestSelfReflectedFeedback;

                if ($this->isInteractionLocked && !$this->currentUser->isSuperuser()) {
                    $data['learnerRubricContext'] = $this->rubricContext->generate($this->assessment, $this->participant->getUser(), $this->currentUser, Access::getInstance(), false);
                } elseif ($this->response) {
                    $data['learnerRubricContext'] = $this->rubricContext->generateMarkableForResponseAndSelfReflection($this->assessment, $this->participant->getUser(), $this->response->getId());
                } else {
                    $data['learnerRubricContext'] = $this->rubricContext->generateMarkable($this->assessment, $this->participant->getUser());
                }
            }

            /* Need this to determine localisation string for headings
             *
             * This accommodates the scenario when the assessment's self-reflection ability has been turned off, but a
             * self-reflection exists for the submission currently being viewed
             */
            if (null !== $latestSelfReflectedFeedback->getNormalisedMark()) {
                $data['selfAssessed'] = true;
            } else {
                $data['selfReflected'] = true;
            }
        }

        return $data;
    }
}