<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent;

use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Learning\Assessment\Participant;

/**
 * andrew.carlton 44 minutes ago
   > Button logic is on the status of the authoritative
   >    + If it is submitted OR past close/due date - view activity
   >    + If there is a response that is not yet submitted and does have a started at time - resume
   >    + If either no response yet or there is a response but no started at - begin
 *
 * Note that there's an operational assumption here;
 * creating a response means setting all other responses to Authoritative: 0
 * except for the one you just made. This enforces the "one authoritative response at all times"
 * but we have to do it in bidness logic everywhere a response spawns.
 *
 * Also there's a crontab that shuts unsubmitted responses once the assessment reaches its due/close date,
 * much like its quiz attempt counterpart that runs on Help.
 */
class Button
{
    /**
     * Words for the calls-to-action
     */
    private const string LABEL_START = 'Begin';
    private const string LABEL_RESUME = 'Resume';
    private const string LABEL_VIEW = 'View';


    /**
     * Constructor.
     *
     * Contains a route generator so you can pre-build the relevant routes here
     * @param \Aura\Router\Generator $routeGenerator
     */
    public function __construct(private \Aura\Router\Generator $routeGenerator)
    {
    }


    /**
     * Takes a fully-hydrated $participant along with their $response instances and the assessment.
     * From those, decides what the call to action should be on the Summary screen.
     *
     * @param Model $assessment
     * @param null|Participant $participant
     * @return array
     * @throws RouteNotFound
     */
    public function generate(Model $assessment, ?Participant $participant): array
    {
        $now = \Alaress_Date_Format::now();

        /* No participant? You might be a staff member creating this thing since that workflow also lands you at
         * the assessment summary screen. Without a participant you can't do much here.
         *
         * I guess...this?
         */
        if (null === $participant) {
            return [
                'label' => self::LABEL_START,
                'responseId' => null,
                'enabled' => false,
                'url' => null,
            ];
        }

        /* If the assessment has surpassed its publish date, but not yet reached its open date,
         * button says "Begin" in a disabled state.
         */
        if ($assessment->getPublishAt()->lte($now) && $assessment->getOpenAt()->gt($now)) {
            /* Begin (disabled)
             */
            return [
                'label' => self::LABEL_START,
                'responseId' => null,
                'enabled' => false,
                'url' => null,
            ];
        }

        /* Everything below here requires you to know about an authoritative response, if extant.
         */

        /** @var null|\Schoolbox\Learning\Assessment\DialogAgent\Response\Model $authoritativeResponse */
        $authoritativeResponse = null;

        if ($participant->getAllResponses()->count()) {
            $authoritativeResponse = $participant->getAllResponses()->filter(static function ($response) {
                return $response->getAuthoritative();
            })->first();
        }

        /* The assessment has surpassed its due/close date, but we need to let students see the work they did
         * previously. This lets them use it for their own revision purposes. Note that dialog agents' Due and
         * Close date should be the same value: this is enforced elsewhere in the swamp.
         */
        if ($assessment->getCloseAt()->lte($now)) {
            if (null === $authoritativeResponse) {
                /* No authoritative responses; the student never did the work
                 * Button says "View" in a disabled state.
                 */
                return [
                    'label' => self::LABEL_VIEW,
                    'responseId' => null,
                    'enabled' => false,
                    'url' => null,
                ];
            } else {
                /* If the assessment has surpassed its due date,
                 * but this is an authoritative response,
                 * button says "View" but lets you continue so you can look at it.
                 */
                return [
                    'label' => self::LABEL_VIEW,
                    'responseId' => $authoritativeResponse->getId(),
                    'enabled' => true,
                    'url' => $this->routeGenerator->generate('learningAssessment.getViewResponse', [
                        'assessment' => $assessment,
                        'assessmentId' => $assessment->getId(),
                        'student' => $participant->getUser(),
                        'studentId' => $participant->getUser()->getId(),
                        'response' => $authoritativeResponse,
                        'responseId' => $authoritativeResponse->getId()
                    ]),
                ];
            }
        }

        /* Otherwise it's reached its open date, but it's not closed yet; deal with responses somewhat intricately
         * This mega clause represents students -doing stuff-
         */
        if ($assessment->getOpenAt()->lte($now)) {
            if (!$participant->getAllResponses()->count()) {
                /* If there are no responses at all, button says "Begin"
                 * You're about to make the first one and the first one is always authoritative.
                 */
                return [
                    'label' => self::LABEL_START,
                    'responseId' => null,
                    'enabled' => true,
                    'url' => $this->routeGenerator->generate('learningAssessment.getCreateResponseForm', [
                        'assessment' => $assessment,
                        'assessmentId' => $assessment->getId(),
                        'student' => $participant->getUser(),
                        'studentId' => $participant->getUser()->getId(),
                    ]),
                ];
            }

            /* Otherwise, there are existing response(s); dig deeper.
             *
             * Look for an authoritative response. If there is one:
             *     - if it has a submitted_at, button says "View"
             *     - otherwise, button says "Resume"
             */
            if (null !== $authoritativeResponse) {
                if (null !== $authoritativeResponse->getSubmittedAt()) {
                    /* View (enabled)
                     */
                    return [
                        'label' => self::LABEL_VIEW,
                        'responseId' => $authoritativeResponse->getId(),
                        'enabled' => true,
                        'url' => $this->routeGenerator->generate('learningAssessment.getViewResponse', [
                            'assessment' => $assessment,
                            'assessmentId' => $assessment->getId(),
                            'student' => $participant->getUser(),
                            'studentId' => $participant->getUser()->getId(),
                            'response' => $authoritativeResponse,
                            'responseId' => $authoritativeResponse->getId()
                        ]),
                    ];
                } else {
                    /* Resume (enabled)
                     */
                    return [
                        'label' => self::LABEL_RESUME,
                        'responseId' => $authoritativeResponse->getId(),
                        'enabled' => true,
                        'url' => $this->routeGenerator->generate('learningAssessment.getResumeResponse', [
                            'assessment' => $assessment,
                            'assessmentId' => $assessment->getId(),
                            'student' => $participant->getUser(),
                            'studentId' => $participant->getUser()->getId(),
                            'response' => $authoritativeResponse,
                            'responseId' => $authoritativeResponse->getId()
                        ]),
                    ];
                }
            }

            /* Note that the else clause here would represent a scenario where the first response WAS NOT set to
             * be authoritative, which is a mistake on our part. The first response is always authoritative and
             * there is always at least one authoritative response per assessment (unless the student literally
             * did nothing to contribute to this activity)
             */
        }

        /* Assessment Duration
         *
         * Note that durations are -per response- and mostly enforced in frontend.
         * Backend implements a crontab that forcibly shuts whatever the frontend couldn't.
         * Durations don't factor into whether you can make responses or anything, it's a clerical duty.
         */

        /* Unforeseen scenario.
         */
        return [
            'label' => self::LABEL_START,
            'responseId' => null,
            'enabled' => false,
            'url' => null,
        ];
    }
}
