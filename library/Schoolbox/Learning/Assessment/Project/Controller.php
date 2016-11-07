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

class Controller extends Assessment\Controller
{

    /**
     * @param Assessment\Project|IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment)
    {
        $summatorMapping = new Assessment\Summator\Mapping();

        $data = [
            'types'            => new Assessment\AssessmentType(),
            'selectedSummator' => $summatorMapping->decompose($assessment->getSummator()),
            'allSummators'     => $summatorMapping,
            'allMarkTypes'    => $this->getAllMarkTypes($assessment),
            'allGradeSchemes' => $this->getAllGradeSchemes(),
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

        $counts = [];
        $assessableCounts = [];
        foreach ($participants as $participant) {
            $counts[$participant->getUser()->getId()] = [
                'total' => 0,
                'totalCompleted' => 0,
            ];
        }

        // This isn't necessarily a project.
        foreach ($assessment->getAssessables() as $subAssessment) {
            $assessableCounts[$subAssessment->getId()]['total'] = 0;

            // Count participants for every single sub-assessment
            foreach ($subAssessment->getAllParticipants() as $participant) {
                $counts[$participant->getUser()->getId()]['total']++;
                $assessableCounts[$subAssessment->getId()]['total']++;

                if ($participant->getAllResponses()->count() > 0) {
                    $counts[$participant->getUser()->getId()]['totalCompleted']++;
                    $assessableCounts[$subAssessment->getId()]['totalCompleted']++;
                }
            }
        }

        /* Manufacture a universal returnUrl
         * Return to summary screen with this suffix
         */
        $baseUrl = $this->getRouteIfAuthorized('learningAssessment.getSummary', ['assessmentId' => $assessment->getId()]);
        $returnUrlSuffix = '?'.Redirect::RETURN_URL.'=' . Redirect::encode($baseUrl);

        $twigData = [
            'breadcrumbs'      => $this->getBakery()->produceFromAssessment($assessment),
            'counts'           => $counts,
            'assessableCounts' => $assessableCounts,
            'assessment'       => $assessment,
            'currentUser'      => $this->getUser(),
            'returnUrlSuffix'  => $returnUrlSuffix,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/protject/status/staff.twig",
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
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment),
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
     * @param string      $defaultReturnUrl
     * @return string
     */
    protected function determineRedirect(IAssessable $assessment, $defaultReturnUrl = '')
    {
        // at the end of a mutation, a project will redirect back to its status screen
        return $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $assessment->getId() ]);
    }


}
