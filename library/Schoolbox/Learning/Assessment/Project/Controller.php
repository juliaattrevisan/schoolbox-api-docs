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
use Schoolbox\Application\Exception as Application;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Error;

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
     * Display appropriate landing page based on role
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @level 165
     * @param IAssessable $assessment
     */
    public function getPreview(IAssessable $assessment)
    {

        /** @var Assessment\Project $project */
        $project = $assessment;

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

        $subActivities = $project->getAssessables();

        // obtain user access level
        $access = $this->getAccess()->getFolderAccess($this->getUser(), $project->getBaseContext()->getFolder()->getId());

        if ($access < UserAccess::ACCESS_WRITE) {
            // if you have read or less, you have additional restrictions
            if ($project->getPublishAt()->isFuture()) {
                // project is not
                throw $this->getErrorMapper()->generateException(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW);
            }

            // this isn't cached in the factory method... for some reason...
            $now = Alaress_Date_Format::now();

            // filter out any sub-activities that have not yet been published
            $subActivities = $subActivities->filter(function (IAssessable $activity) use ($now) {
                return $activity->getPublishAt()->lte($now);
            });

            // hack the project object for good measure
            $project->setAssessables($subActivities);
        }

        /** @var IAssessable $subActivity */
//        foreach ($subActivities as $subActivity) {
//
//            if (!$subActivity->isParticipant($student)) {
//                // This student isn't a participant.
//                // This is fine - when PBL is implemented not all students
//                // will participate in all sub-assessments
//                continue;
//            }
//
//            $type = Assessment\AssessmentType::objectToString($subActivity);
//
//            // If the student has submitted at least one response we will assume it is completed
//            $counts[$type]++;
//            $counts['total']++;
//
//            if ($participant->getAllResponses()->count() > 0) {
//                $counts["{$type}Completed"]++;
//                $counts['totalCompleted']++;
//            }
//        }

        // Ideally make this an instance var instead of instantiating all the time
        // Except you can't, $this->getRouter() returns null in __construct()?
        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($project, $this->getUser())->add([ $project->getTitle(), '' ]),
            'assessment'  => $project,
            'counts'      => $counts,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/project/summary/preview.twig",
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
     * @throws ServiceNotFound If unable to find web renderer
     * @throws Application\Exception If project in future and user has <= READ access
     */
    public function getSummary(Participant $participant)
    {
        /** @var Assessment\Project $project */
        $project = $participant->getAssessable();
        $student = $participant->getUser();

        $subActivities = $project->getAssessables();

        // filter out any sub-activities that have not yet been published
        $visibility    = new Assessment\Visibility($this->getAccess());
        $now           = Alaress_Date_Format::now();
        $mutator       = new Participant\AssessmentListMutator($this->getUser(), $visibility, $now, true);
        $subActivities = $subActivities
            ->map([$mutator, 'map'])
            ->filter(function (IAssessable $activity) use ($student, $visibility, $mutator, $now) {
                return $mutator->filter($activity)
                    && $visibility->isVisibleToLearner($activity, $student, true, $now);
            });

        // hack the project object for good measure
        $project->setAssessables($subActivities);


        // Ideally make this an instance var instead of instantiating all the time
        // Except you can't, $this->getRouter() returns null in __construct()?
        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($project, $this->getUser())->add([ $project->getTitle(), '' ]),
            'student'     => $student,
            'assessment'  => $project,
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
