<?php
/**
 * StudentCarousel
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\InlineGrader;

use Aura\Router\Router;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
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
     * StudentCarousel constructor.
     *
     * @param Router $router
     * @param User $student
     * @param DueWork $assessment
     */
    public function __construct(Router $router, User $student, DueWork $assessment)
    {
        $this->router     = $router;
        $this->student    = $student;
        $this->assessment = $assessment;
    }

    /**
     * Produce a (currentPerson, allPeople) tuple to populate the
     * student selector carousel on getParticularResponse
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

        foreach ($this->assessment->getAllParticipants() as $currentParticipant) {
            // Skip, if excluded from the assessment
            if ($currentParticipant->isExcluded()) {
                continue;
            }

            // CSS/label rules
            // TODO move CSS/label generation to participant class, and dedupe usages
            if($currentParticipant->isResubmissionRequired()) {
             $css = 'alert';
             $label = 'Resubmit Required';
            }
            else if ($currentParticipant->isReviewComplete()) {
                // Is review complete? Always say "Reviewed"
                $css   = 'reviewed';
                $label = 'Reviewed';


            } else {
                $latestSubmission = $currentParticipant->findMostRecentSubmittedResponse();

                /** @var Submission $latestSubmission */
                if ($latestSubmission) {
                    $multipleResponses = \count($currentParticipant->getAllResponses()) > 1;
                    if ($latestSubmission->isSubmittedOnTime()) {
                        $css   = 'submitted';
                        $label = $multipleResponses ? 'Resubmitted on time' : 'Submitted on time';

                    } else {
                        $css   = 'submitted-late';
                        $label = $multipleResponses ? 'Resubmitted late' : 'Submitted late';
                    }
                } else {
                    /* No submission exists
                     */
                    $css   = 'unsubmitted';
                    $label = $this->assessment->isAcceptNone() ? 'Not reviewed' : 'Not submitted';
                }
            }

            /* Viewing the most recent response for the next student
             */
            $urlValue = $this->router->generate('learningDueWorkResponse.getParticularResponse', [
                'assessmentId' => $this->assessment->getId(),
                'studentId'    => $currentParticipant->getUser()->getId(),
                'assessment'   => $this->assessment
            ]);

            $person = [
                'text' => $currentParticipant->getUser()->getFullname(),
                'value' => $urlValue,
                'html' => "
                    <div class=\"row\">
                        <div class=\"card small-10\">
                            <img src=\"/portrait.php?userId={$currentParticipant->getUser()->getExternalId()}&size=square64\" alt=\"View Profile\" />
                            <p>{$currentParticipant->getUser()->getFullName()}</p>
                            <span class=\"label status {$css}\">{$label}</span>
                        </div>
                    </div>
                ",
                'submitted' => $currentParticipant->isFeedbackRequired(),
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
        return $this->router->generate('learningDueWorkResponse.getParticularResponse', [
            'assessmentId' => $this->assessment->getId(),
            'studentId'    => $this->student->getId(),
            'assessment'   => $this->assessment
        ]);
    }
}
