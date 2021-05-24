<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\StudentCarousel;

use Aura\Router\Router;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

/**
 * Class StudentCarousel
 *
 * A cyclic selectize element that appears at the top of Inline Grading
 * Allows you to load a student's response, mark it, then visit the next
 * (or previous) guy in the list
 *
 * @package Schoolbox\Learning\InlineGrader\StudentCarousel
 */
class Standard
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
     * Standard constructor.
     *
     * @param Router      $router
     * @param User        $student
     * @param DueWork     $assessment
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
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function produce(): array
    {
        return [
            'currentPerson' => $this->getUrl($this->student),
            'allPeople' => $this->getAllPeople(),
        ];
    }


    /**
     * Filter out users we don't want in the Carousel
     *
     * @return Collection|Participant[]
     */
    private function prefilterParticipants()
    {

        /* Always disregard Excluded user
         */
        return $this->assessment->getAllParticipants()->filter(function (Participant $currentParticipant) {
            return !$currentParticipant->isExcluded();
        });
    }


    /**
     * Get a list of possible participants and whether they've submitted stuff
     */
    private function getAllPeople(): array
    {
        $allPeople = [];
        $allParticipants = $this->prefilterParticipants();

        foreach ($allParticipants as $currentParticipant) {
            // CSS/label rules
            // TODO move CSS/label generation to participant class, and dedupe usages
            if ($currentParticipant->isResubmissionRequired()) {
                $css = 'alert';
                $label = 'Resubmit Required';
            } elseif ($currentParticipant->isReviewComplete()) {
                // Is review complete? Always say "Reviewed"
                $css   = 'reviewed';
                $label = 'Reviewed';
            } else {
                $latestSubmission = $currentParticipant->findMostRecentSubmittedResponse();

                /** @var Submission $latestSubmission */
                if ($latestSubmission) {
                    $multipleResponses = \count($currentParticipant->getAllResponses()) > 1;
                    $inPerson = $latestSubmission->isSubmittedOffline() ? 'in person ' : '';

                    if ($latestSubmission->isSubmittedOnTime()) {
                        $css   = 'submitted';
                        $label = $multipleResponses ? 'Resubmitted ' . $inPerson . 'on time' : 'Submitted ' . $inPerson . 'on time';
                    } else {
                        $css   = 'submitted-late';
                        $label = $multipleResponses ? 'Resubmitted ' . $inPerson . 'late' : 'Submitted ' . $inPerson . 'late';
                    }
                } else {
                    /* No submission exists
                     */
                    $css   = 'unsubmitted';
                    $label = $this->assessment->isAcceptNone() ? 'Not reviewed' : 'Not submitted';
                }
            }

            $html = "
                <div class=\"row\">
                    <div class=\"card small-10\">
                        <img src=\"/portrait.php?userId={$currentParticipant->getUser()->getExternalId()}&size=square64\" alt=\"View Profile\" />
                        <p>{$currentParticipant->getUser()->getFullName()}</p>
                        <span class=\"label status {$css}\">{$label}</span>
                    </div>
                </div>
            ";

            $person = [
                'text' => $currentParticipant->getUser()->getFullname(),
                'value' => $this->getUrl($currentParticipant->getUser()),
                'html' => $html,
                'submitted' => $currentParticipant->isFeedbackRequired(),
            ];

            $allPeople[] = $person;
        }

        return $allPeople;
    }


    /**
     * Get the url corresponding to this person, to preselect selectize
     *
     * @param User $user
     * @return string
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function getUrl(User $user): string
    {
        $url = $this->router->generate('learningDueWorkResponse.getParticularResponse', [
            'assessmentId' => $this->assessment->getId(),
            'studentId'    => $user->getId(),
            'assessment'   => $this->assessment
        ]);

        return (string)$url;
    }
}
