<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Project;

use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Class Controller
 *
 * @package Schoolbox\Learning\Assessment\Project
 */
class Controller extends Assessment\Controller
{

    /**
     * @param Assessment\Project|IAssessable $assessment
     * @return array
     * @throws UnexpectedValueException
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $summatorMapping = new Assessment\Summator\Mapping();

        try {
            $selectedSummator = $summatorMapping->decompose($assessment->getSummator());
        } catch (InvalidArgumentException $e) {
            //no selected summator if none exists
            $selectedSummator = null;
        }

        return [
            'types'             => new Assessment\AssessmentType(),
            'selectedSummator'  => $selectedSummator,
            'acceptingResponses' => false,
            'allSummators'      => $summatorMapping,
            'allMarkTypes'      => $this->getAllMarkTypes($assessment),
            'allGradeSchemes'   => $this->getAllGradeSchemes(),
        ];
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
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
     * @since 165
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
    public function getStatus(IAssessable $assessment): void
    {

        // Calculate summary totals for display purposes
        $participants = $assessment->getAllParticipants();

        $assessmentStats = new GenericCollection(Assessment\Statistics::class);
        $assessmentStats[$assessment->getId()] = new Assessment\Statistics($assessment);
        foreach ($assessment->getAssessables() as $subAssessable) {
            $assessmentStats[$subAssessable->getId()] = new Assessment\Statistics($subAssessable);
        }

        $participantStats = new GenericCollection(Assessment\ParticipantStatistics::class);
        foreach ($participants as $participant) {
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
     * @since 165
     * @param IAssessable|Project $assessment
     * @param User                $student
     * @throws ServiceNotFound If unable to find web renderer
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws Exception
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        $subActivities = $assessment->getAssessables();

        // filter out any sub-activities that have not yet been published
        $visibility    = new Assessment\Visibility($this->getAccess());
        $now           = Alaress_Date_Format::now();
        $mutator       = new Participant\AssessmentListMutator($this->getUser(), $visibility, $now, true);
        $classFolder          = $assessment->getBaseContext()->getFolder();
        $isViewerAnInstructor = $this->getAccess()->isInstructor($this->getUser(), $classFolder);

        // If $student is an instructor, all assessments appear on project summary page
        // *except for* assessments which the instructor has participated to and then has been excluded from.

        $subActivities = $subActivities
            ->map([$mutator, 'map'])
            ->filter([$mutator, 'filter'])
            ->filter(static function (IAssessable $subAssessable) use ($student, $isViewerAnInstructor) {
                try {
                    $participant = $subAssessable->getParticipant($student);
                    return !$participant->isExcluded();
                } catch (UnexpectedValueException $e) {
                    // not participant
                    return $isViewerAnInstructor;
                }
            });

        // hack the project object for good measure
        $assessment->setAssessables($subActivities);

        // How should the jewelry transition?
        $tickTransitions      = [
            'incomplete'      => $isViewerAnInstructor ? 'teacherAssessed' : 'selfAssessed',
            'selfAssessed'    => $isViewerAnInstructor ? 'teacherAssessed' : 'incomplete',
            'teacherAssessed' => 'incomplete',
        ];


        // Ideally make this an instance var instead of instantiating all the time
        // Except you can't, $this->getRouter() returns null in __construct()?
        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '' ]),
            'student'     => $student,
            'assessment'  => $assessment,
            'transitions'  => $tickTransitions,
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
     * @param string|null $postReturnUrl
     * @param string      $defaultReturnUrl
     * @param bool        $relocated
     * @return string
     * @throws RouteNotFound
     */
    public function determineRedirect(IAssessable $assessment, $postReturnUrl = null, $defaultReturnUrl = '', bool $relocated = false): string
    {
        /**
         * Always go to the status screen when a project has been created,
         * but send the return url with it
         */
        $redirect = $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $assessment->getId() ]);

        if ($postReturnUrl) {
            $redirect .= '?' . Redirect::RETURN_URL . "=$postReturnUrl";
        }

        return $redirect;
    }
}
