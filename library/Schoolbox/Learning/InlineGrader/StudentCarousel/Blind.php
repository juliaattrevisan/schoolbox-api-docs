<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

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
 * There is a Blind Mode option that obfuscates identifying features for
 * each student, and changes their order to something not otherwise seen
 * in the Status screen to help mentally disconnect the marker from the
 * student whom they are marking but if you're familiar with the student's
 * work you'll probably recognise them regardless.
 *
 * To mix the order up we just order by id. I did consider randomising
 * with shuffle() or something, but it has to persist between next/prev
 * selections, and that's annoying
 *
 * @package Schoolbox\Learning\InlineGrader\StudentCarousel
 */
class Blind
{

    /**
     * @var Router
     */
    private $router;


    /**
     * @var User|null
     */
    private $student;


    /**
     * @var DueWork
     */
    private $assessment;


    /**
     * Blind constructor.
     *
     * @param Router    $router
     * @param User|null $student
     * @param DueWork   $assessment
     */
    public function __construct(Router $router, ?User $student, DueWork $assessment)
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
            'currentPerson' => $this->student ? $this->getUrl($this->student) : '',
            'allPeople' => $this->getAllPeople(),
        ];
    }


    /**
     * Find the first blind-reviewable person's url
     * If none exist, return null
     */
    public function getFirstUrl(): ?string
    {
        return $this->getAllPeople()[0]['value'] ?? null;
    }


    /**
     * Filter out users we don't want in the Carousel
     * Blind Mode returns a subset of vanilla inline grader mode
     *
     * @return Collection|Participant[]
     */
    private function prefilterParticipants()
    {
        $allParticipants = $this->assessment->getAllParticipants();

        /* Always disregard Excluded user
         *
         * Filter out guys so that all who remain are students who have submitted a Response
         * electronically (online) and those Responses require published Feedback
         */
        $allParticipants = $allParticipants->filter(function (Participant $currentParticipant) {
            return !(
                $currentParticipant->isExcluded() ||
                $currentParticipant->isResubmissionRequired() ||
                $currentParticipant->isReviewComplete());
        });

        /* Change the ordering around some more, for Blind Mode
         */
        $allParticipants = $allParticipants->sortByCallback(function (Participant $p1, Participant $p2) {

            /* You could probably throw in some more obfuscating ideas
             * like salting the comparison with today's date, such that
             * revisiting Blind Mode tomorrow gives you a different order
             *
             * This sorts by user id reversed, to make the marking user
             * do more mental gymnastics if they want to guess identities
             */
            return $p1->getUser()->getId() >= $p2->getUser()->getId();
        });

        return $allParticipants;
    }


    /**
     * Get a list of possible participants and whether they've submitted stuff
     */
    private function getAllPeople(): array
    {
        $allPeople = [];
        $allParticipants = $this->prefilterParticipants();

        foreach ($allParticipants as $currentParticipant) {

            /** @var Submission $latestSubmission */
            $latestSubmission = $currentParticipant->findMostRecentSubmittedResponse();

            /* Skip unsubmitted
             */
            if (!$latestSubmission) {
                continue;
            }

            /* Skip Offline submissions when student hands in work in-person
             */
            if ($latestSubmission->isSubmittedOffline()) {
                continue;
            }

            $multipleResponses = \count($currentParticipant->getAllResponses()) > 1;

            if ($latestSubmission->isSubmittedOnTime()) {
                $css   = 'submitted';
                $label = $multipleResponses ? 'Resubmitted on time' : 'Submitted on time';
            } else {
                $css   = 'submitted-late';
                $label = $multipleResponses ? 'Resubmitted late' : 'Submitted late';
            }

            /* Decide if we need an obfuscating class for Blind Mode
             * This keeps the image blur from expanding beyond the dimensions we wanted
             */
            $html = "
                <div class=\"row\">
                    <div class=\"card small-10\">
                        <div class='blind-container'>
                            <img src=\"/portrait.php?id={$currentParticipant->getUser()->getId()}&size=square64\" class=\"blind\" alt=\"View Profile\" />
                        </div>
                        <p class=\"blind\" >{$currentParticipant->getUser()->getFullName()}</p>
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
        $url = $this->router->generate('learningDueWorkResponse.getParticularResponseBlind', [
            'assessmentId' => $this->assessment->getId(),
            'studentId'    => $user->getId(),
            'assessment'   => $this->assessment
        ]);

        return (string)$url;
    }
}
