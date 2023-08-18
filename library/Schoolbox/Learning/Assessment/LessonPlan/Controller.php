<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan;

use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\User;

class Controller extends Assessment\Controller
{
    /**
     * Construct an arbitrary array of data for twig rendering
     *
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $zoneFactory = new Assessment\Attachzone\Factory();

        return [
            /* This is only used when creating assessments outside of the Course Import
             * workflow. Lesson Plans must always go through Course Import so this var
             * isn't really used at all
             */
            'acceptingResponses' => false,
            'attachzone' => $zoneFactory->buildForAssessment($assessment)->getTwigContext(),
        ];
    }


    /**
     * Lesson Plans don't have any feedbacks.
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function getStatus(IAssessable $assessment): void
    {
        // noop
    }


    /**
     * Assessment Summary Screen for a Lesson Plan
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        $isViewerAnInstructor = $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder());

        /* Craft a returnUrl, to redirect back to the place from whence you came
         */
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        /**
         * record view
         */
        $lastView = new Assessment\Views\LatestView(
            $this->latestViewMapper,
            new \Schoolbox\External\User(
                $this->getConfigLoader()->get('external_type'),
                new \External_Class(),
                new \External_User(),
                $this->getUserMapper()
            )
        );

        $lastView->addNewView($assessment, $student, $this->getUser());

        $twigData = [
            'lastViews'    => $lastView->getTwigViews($assessment, $student, $isViewerAnInstructor),
            'breadcrumbs'  => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'   => $assessment,
            'student'      => $student,
            'returnUrl'    => $returnUrl,

            /* FIXME Controversial:
             *  whether "staff-only fields" really means "instructor-only fields",
             *  or whether it's strictly for the Role::STAFF demographic? Non-staff
             *  roletypes can end up fulfilling Instructor responsibilities
             */
            'isInstructor' => $isViewerAnInstructor,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/lessonPlan/summary/student.twig',
            $twigData
        );
    }


    /**
     * Lesson Plans don't have any responses.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        // noop
    }


    /**
     * Lesson Plans don't have any responses.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // noop
    }

    /**
     * APIs
     */
    public function getActivities(Model $assessment): void
    {
        $this->response = $this->responseAsJson($assessment->getLinkedAssessments());
    }

    public function postActivity(Model $assessment, IAssessable $linkedAssessment): void
    {
        $newLink = $assessment->setLinkedAssessments($assessment->getLinkedAssessments()->add($linkedAssessment));
        $this->getAssessmentMapper()->save($newLink, $this->getUser());

        $this->response = $this->responseAsJson($newLink->getLinkedAssessments());
    }

    public function deleteActivity(Model $assessment, IAssessable $linkedAssessment): void
    {
        $linkedAssessmentsMapper = Assessment\DataModel\LessonPlan\LinkedAssessments\Mapper::getInstance();
        $return = $linkedAssessmentsMapper->deleteLink($assessment, $linkedAssessment);
        if (0 === $return) {
            throw new NotFound();
        }
        $this->response = $this->responseEmpty();
    }
}
