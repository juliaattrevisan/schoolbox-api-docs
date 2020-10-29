<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\InlineGrader;

use Aura\Router\Router;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
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
 * @package Schoolbox\Learning\InlineGrader
 */
class StudentCarousel
{

    /**
     * The querystring parameter corresponding to Blind Marking
     * If it's set, presume that Blind Marking was desired; starts the
     * Student Carousel in a special way if set int he url
     */
    public const QUERYSTRING_BLIND_MARKING = 'b';


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
     * @var string
     */
    private $blindModeParams = '';


    /**
     * StudentCarousel constructor.
     *
     * @param Router      $router
     * @param User        $student
     * @param DueWork     $assessment
     * @param string|null $blindMode
     */
    public function __construct(Router $router, User $student, DueWork $assessment, ?string $blindMode)
    {
        $this->router     = $router;
        $this->student    = $student;
        $this->assessment = $assessment;

        /* Decide whether we need to work with Blind Mode querystring params
         */
        if ((bool)$blindMode) {
            $this->blindModeParams = '?' . http_build_query([
                self::QUERYSTRING_BLIND_MARKING => 1
            ]);
        }
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
            'currentPerson' => $this->getCurrentPerson(),
            'allPeople' => $this->getAllPeople(),
        ];
    }


    /**
     * Get a list of possible participants and whether they've submitted stuff
     */
    private function getAllPeople(): array
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

            /* Viewing the most recent response for the next student
             */
            $urlValue = $this->router->generate('learningDueWorkResponse.getParticularResponse', [
                'assessmentId' => $this->assessment->getId(),
                'studentId'    => $currentParticipant->getUser()->getId(),
                'assessment'   => $this->assessment
            ]);

            $person = [
                'text' => $currentParticipant->getUser()->getFullname(),
                'value' => "{$urlValue}{$this->blindModeParams}",
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
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function getCurrentPerson()
    {
        return $this->router->generate('learningDueWorkResponse.getParticularResponse', [
            'assessmentId' => $this->assessment->getId(),
            'studentId'    => $this->student->getId(),
            'assessment'   => $this->assessment
        ]) . $this->blindModeParams;
    }
}
