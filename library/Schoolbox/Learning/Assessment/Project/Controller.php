<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Project;

use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\User;

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
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     * @throws NotFound
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        // route not implemented
        throw new NotFound();
    }


    /**
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @throws NotFound
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
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
     * @throws ServiceNotFound
     */
    public function getStatus(IAssessable $assessment)
    {

        // Calculate summary totals for display purposes
        $participants = $assessment->getAllParticipants();

        $assessmentStats = new GenericCollection(Assessment\Statistics::class);
        $assessmentStats[$assessment->getId()] = new Assessment\Statistics($assessment);
        foreach($assessment->getAssessables() as $subAssessable){
            $assessmentStats[$subAssessable->getId()] = new Assessment\Statistics($subAssessable);
        }

        $participantStats = new GenericCollection(Assessment\ParticipantStatistics::class);
        foreach($participants as $participant){
            $participantStats[$participant->getUser()->getId()] = new Assessment\ParticipantStatistics($participant);
        }

        $twigData = [
            'breadcrumbs'       => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle()]),
            'baseContext'       => $assessment->getBaseContext(),
            'assessment'        => $assessment,
            'assessmentStats'   => $assessmentStats,
            'participantStats'  => $participantStats,
            'returnUrl'         => Redirect::decode($this->getRequest()->query->get(Redirect::RETURN_URL))
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/project/status/staff.twig',
                $twigData
            )
        );
    }



    /**
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @level 165
     * @param IAssessable|Project $assessment
     * @param User                $student
     * @throws ServiceNotFound If unable to find web renderer
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        $subActivities = $assessment->getAssessables();

        // filter out any sub-activities that have not yet been published
        $visibility    = new Assessment\Visibility($this->getAccess());
        $now           = Alaress_Date_Format::now();
        $mutator       = new Participant\AssessmentListMutator($this->getUser(), $visibility, $now, true);
        $subActivities = $subActivities
            ->map([$mutator, 'map'])
            ->filter([$mutator, 'filter'])
            ->filter(function(IAssessable $subAssessable) use ($student) {
                // If a parent, only show sub-assessments if the current viewee is participating
                return !(!$subAssessable->isParticipant($student) && $this->getUser()->getRole()->isParent());
            });

        // hack the project object for good measure
        $assessment->setAssessables($subActivities);

        // Ideally make this an instance var instead of instantiating all the time
        // Except you can't, $this->getRouter() returns null in __construct()?
        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '' ]),
            'student'     => $student,
            'assessment'  => $assessment,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/project/summary/student.twig',
                $twigData
            )
        );

    }


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $explicitReturnUrl
     * @param string      $defaultReturnUrl
     * @return string
     */
    public function determineRedirect(IAssessable $assessment, $explicitReturnUrl = null, $defaultReturnUrl = '')
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
