<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Project;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\AssessmentContext;

class Controller extends Assessment\Controller
{
    /**
     * @param Assessment\Project|IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment)
    {
        $summatorMapping = new Assessment\Summator\Mapping();

        try {
            $selectedSummator = $summatorMapping->decompose($assessment->getSummator());
        } catch (\InvalidArgumentException $e) {
            //no selected summator if none exists
            $selectedSummator = null;
        }

        $data = [
            'types'             => new Assessment\AssessmentType(),
            'selectedSummator'  => $selectedSummator,
            'acceptingResponses'=> false,
            'allSummators'      => $summatorMapping,
            'allMarkTypes'      => $this->getAllMarkTypes($assessment),
            'allGradeSchemes'   => $this->getAllGradeSchemes(),
        ];

        return $data;
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function getCreateResponseForm(Participant $participant)
    {
        // route not implemented
        throw new NotFound();
    }


    /**
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function postCreateResponse(Participant $participant)
    {
        // route not implemented
        throw new NotFound();
    }


    /**
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @param Project|IAssessable $assessment
     */
    public function getStatus(IAssessable $assessment)
    {

        // Calculate summary totals for display purposes
        $participants = $assessment->getAllParticipants();

        /* The number of assessments that have been completed by
         * their respective total number of participants
         */
        $participantCounts = [
            'completed' => [],
            'totalAssessments' => $assessment->getAssessables()->count()
        ];

        /* The number of participants who have completed their
         * respective total number of assessments
         */
        $assessmentCounts = [
            'completed' => [],
            'totalParticipants' => $participants->count()
        ];

        /* A tally per-participant
         */
        foreach ($participants as $currentParticipant) {
            $participantCounts['completed'][$currentParticipant->getUser()->getId()] = 0;
        }

        /* A tally per-assessment
         */
        foreach ($assessment->getAssessables() as $currentAssessment) {

            /* Initialise count of IResponses for $currentAssessment
             */
            $assessmentCounts['completed'][$currentAssessment->getId()] = 0;

            /* Count the number of IResponses submitted by each participant in $currentAssessment
             * and update both tallies
             */
            foreach ($currentAssessment->getAllParticipants() as $currentParticipant) {

                /* $currentParticipant has an IResponse for $currentAssessment
                 */
                if ($currentParticipant->getAllResponses()->count()) {
                    $assessmentCounts['completed'][$currentAssessment->getId()]++;
                    $participantCounts['completed'][$currentParticipant->getUser()->getId()]++;
                }
            }
        }

        /* Manufacture a universal returnUrl
         * Return to summary screen with this suffix
         */
        $baseUrl = $this->getRouteIfAuthorized('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]);
        $returnUrlSuffix = '?'.Redirect::RETURN_URL.'=' . Redirect::encode($baseUrl);

        $twigData = [
            'breadcrumbs'       => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle()]),
            'baseContext'       => $assessment->getBaseContext(),
            'assessment'        => $assessment,
            'participantCounts' => $participantCounts,
            'assessmentCounts'  => $assessmentCounts,
            'currentUser'       => $this->getUser(),
            'returnUrlSuffix'   => $returnUrlSuffix,
            'returnUrl'         => Redirect::decode($this->getRequest()->query->get(Redirect::RETURN_URL))
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/project/status/staff.twig",
                $twigData
            )
        );
    }

    /**
     * Display appropriate landing page based on role
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @level 165
     * @param Participant $participant
     */
    public function getSummary(Participant $participant)
    {
        $this->assertParticipantAccess($participant);

        /** @var Assessment\Project $assessment */
        $assessment = $participant->getAssessable();
        $student    = $participant->getUser();

        $counts = [];

        // Calculate summary totals for display purposes
        foreach (Assessment\AssessmentType::getStringValues() as $type) {
            if (Assessment\AssessmentType::TYPE_PROJECT == $type) {
                continue;
            }

            $counts[$type]              = 0;
            $counts["{$type}Completed"] = 0;
        }

        $counts['total']          = 0;
        $counts['totalCompleted'] = 0;

        $assessables = $assessment->getAssessables();

        /** @var IAssessable $assessable */
        foreach ($assessables as $assessable) {

            try {
                $participant = $assessable->getParticipant($student);
            } catch (\UnexpectedValueException $e) {
                // This student isn't a participant.
                // This is fine - when PBL is implemented not all students
                // will participate in all sub-assessments
                continue;
            }

            $type = Assessment\AssessmentType::objectToString($assessable);

            // If the student has submitted at least one response we will assume it is completed
            $counts[$type]++;
            $counts['total']++;

            if ($participant->getAllResponses()->count() > 0) {
                $counts["{$type}Completed"]++;
                $counts['totalCompleted']++;
            }
        }

        // Ideally make this an instance var instead of instantiating all the time
        // Except you can't, $this->getRouter() returns null in __construct()?
        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'currentUser' => $this->getUser(),
            'student'     => $student,
            'assessment'  => $assessment,
            'counts'      => $counts,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/project/summary/student.twig",
                $twigData
            )
        );

    }


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string      $explicitReturnUrl
     * @param string      $defaultReturnUrl
     * @return string
     */
    protected function determineRedirect(IAssessable $assessment, $explicitReturnUrl, $defaultReturnUrl = '')
    {
        /**
         * Always go to the status screen when a project has been created,
         * but send the return url with it
         */
        $redirect = $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $assessment->getId() ]);

        if ($explicitReturnUrl) {
            $redirect .= '?' . Redirect::RETURN_URL . "=$explicitReturnUrl";
        }

        return $redirect;
    }

}
