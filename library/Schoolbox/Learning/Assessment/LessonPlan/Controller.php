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
     * Endpoint for selecting a Lesson Plan Template
     *
     * @return void
     */
    public function getSelection()
    {
    }


    public function getStatus(IAssessable $assessment)
    {
        // TODO: Implement getStatus() method.
    }


    public function getSummary(IAssessable $assessment, User $student): void
    {
        if ($this->getRequest()->query->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->getRequest()->query->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        /**
         * record view
         */
        $isViewerAnInstructor = $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder());

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
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/lessonPlan/summary/student.twig',
                $twigData
            )
        );
    }


    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        // TODO: Implement getCreateResponseForm() method.
    }


    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // TODO: Implement postCreateResponse() method.
    }
}
