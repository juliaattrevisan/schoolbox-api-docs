<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Reporting\Feedback\StudentCarousel;

use Aura\Router\Generator;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\User;

/**
 * Class StudentCarousel
 *
 * A cyclic selectize element that appears at the top of reporting feedback.
 * Allows you to load a student, mark thme, then visit the next (or previous) guy in the list
 *
 * This is a replica of the Inline Grader student carousel; this replica doesn't need to know anything
 * about student submissions though.
 *
 * @package Schoolbox\Learning\Reporting\Feeedback\StudentCarousel
 */
class Standard
{
    /**
     * Standard constructor.
     *
     * @param Generator $routeGenerator
     * @param User $student
     * @param Reporting\Model $assessment
     */
    public function __construct(
        private Generator $routeGenerator,
        private User $student,
        private Reporting\Model $assessment
    ) {
    }


    /**
     * Produce a (currentPerson, allPeople) tuple to populate the
     * student selector carousel
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
     * @return Collection<Participant>
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

            /* Scrub it
             */
            $fullname = \Scrub::text($currentParticipant->getUser()->getFullName());

            $html = "
                <div class=\"row\">
                    <div class=\"card small-10\">
                        <img src=\"/portrait.php?id={$currentParticipant->getUser()->getId()}&size=square64\" alt=\"View Profile\" />
                        <p>{$fullname}</p>
                        <span class=\"label status \" data-test=\"assessment-student-carousel-statusLabel-{$currentParticipant->getUser()->getId()}\"></span>
                    </div>
                </div>
            ";

            $person = [
                'text' => $fullname,
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
        $url = $this->routeGenerator->generate('learningReportingFeedback.getParticularFeedback', [
            'assessmentId' => $this->assessment->getId(),
            'studentId'    => $user->getId(),
            'assessment'   => $this->assessment
        ]);

        return $url;
    }
}
