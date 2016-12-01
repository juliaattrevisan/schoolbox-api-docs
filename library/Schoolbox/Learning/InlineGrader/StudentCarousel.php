<?php
/**
 * StudentCarousel
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\InlineGrader;

use Aura\Router\Router;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

class StudentCarousel
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @var User
     */
    private $student;

    /**
     * @var DueWork
     */
    private $assessment;

    /**
     * @var Submission
     */
    private $response;

    /**
     * StudentCarousel constructor.
     *
     * @param Router $router
     * @param User $student
     * @param IAssessable $assessment
     * @param Submission $response
     */
    public function __construct(Router $router, User $student, IAssessable $assessment, Submission $response)
    {
        $this->setRouter($router);
        $this->setStudent($student);
        $this->setAssessment($assessment);

        /* Used when you're viewing a particular Response
         * Otherwise null, when viewing the Response List
         */
        $this->setResponse($response);
    }

    /**
     * Produce a (currentPerson, allPeople) tuple to populate the
     * student selector carousel on both the Response and ResponseList
     *
     * @return array
     */
    public function produce()
    {
        return [
            'currentPerson' => $this->getCurrentPerson(),
            'allPeople' => $this->getAllPeople(),
        ];
    }

    /**
     * Get a list of possible participants and whether they've submitted stuff
     */
    private function getAllPeople()
    {
        $allPeople = [];

        foreach ($this->getAssessment()->getAllParticipants() as $currentParticipant) {
            $latestSubmission = $currentParticipant->findMostRecentResponse();

            /** @var Submission $latestSubmission */
            if ($latestSubmission) {
                if ($latestSubmission->isSubmitted()) {
                    if ($latestSubmission->isSubmittedOnTime()) {
                        $css = 'submitted';
                        $label = 'Submitted on time';
                    }
                    else {
                        $css   = 'submitted-late';
                        $label = 'Submitted late';
                    }
                }
                else {

                    /* Draft Submission exists
                     */
                    $css = 'unsubmitted';
                    $label = 'Not submitted';
                }
            }
            else {

                /* No submission exists
                 */
                $css = 'unsubmitted';
                $label = 'Not submitted';
            }

            /* Viewing the most recent response for the next student
             */
            $urlValue = $this->getRouter()->generate('learningResponse.getParticularResponse', [
                'assessmentId' => $this->getAssessment()->getId(),
                'studentId'    => $currentParticipant->getUser()->getId()
            ]);

            $person = [
                'text' => $currentParticipant->getUser()->getFullname(),
                'value' => $urlValue,
                'html' => "
                    <div class=\"row\">
                        <div class=\"avatar card small-10\">
                            <img src=\"/portrait.php?userId={$currentParticipant->getUser()->getExternalId()}&size=square64\" alt=\"View Profile\" />
                            <p>{$currentParticipant->getUser()->getFullName()}</p>
                            <span class=\"label status {$css}\">{$label}</span>
                        </div>
                    </div>
                ",
                'submitted' => $currentParticipant->isMarkingRequired(),
            ];

            $allPeople[] = $person;
        }

        return $allPeople;
    }

    /**
     * Get the url corresponding to this person, to preselect selectize
     *
     * @return false|string
     */
    private function getCurrentPerson()
    {
        return $this->getRouter()->generate('learningResponse.getParticularResponse', [
            'assessmentId' => $this->getAssessment()->getId(),
            'studentId'    => $this->getStudent()->getId(),
        ]);
    }

    /* Getters/Setters
     */

    /**
     * @return Router
     */
    private function getRouter()
    {
        return $this->router;
    }

    /**
     * @param Router $router
     * @return $this
     */
    private function setRouter($router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     * @return User
     */
    private function getStudent()
    {
        return $this->student;
    }

    /**
     * @param User $student
     * @return $this
     */
    private function setStudent($student)
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return IAssessable
     */
    private function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param IAssessable $assessment
     * @return $this
     */
    private function setAssessment($assessment)
    {
        $this->assessment = $assessment;
        return $this;
    }

    /**
     * @return Submission
     */
    private function getResponse()
    {
        return $this->response;
    }

    /**
     * @param Submission $response
     * @return $this
     */
    private function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }
}