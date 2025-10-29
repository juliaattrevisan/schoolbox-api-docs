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
 * haha
 *
 * andrew.carlton 44 minutes ago
   > Button logic is on the status of the authoritative
   >    + If it is submitted OR past close/dye date - view activity
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
    public function __construct(private \Aura\Router\Generator $routeGenerator) {
    }


    /**
     * Takes a fully-hydrated $participant along with their $response instances and the assessment.
     * From those, decides what the call to action should be on the Summary screen.
     *
     * @param Model $assessment
     * @param Participant $participant
     * @return array
     * @throws RouteNotFound
     */
    public function generate(Model $assessment, Participant $participant): array
    {
        $now = \Alaress_Date_Format::now();

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

        /* If you've spent the allotted time across all of your responses, you can't make any more responses.
         * button says "View" so you can see the authoritative response.
         *
         * TODO depends on SA-21229 existing.
         */
//        if ($assessment->getDuration() > $totalTime) {
//            /* No authoritative response, but you've used up all your time. This scenario should be prevented by
//             * crontabs forcefully setting submitted_at but in the very possible outcome that crontabs fail, this
//             * will be experienced by users. There have been cases recently where crontabs crashed out entirely.
//             *
//             * This means you can get here by a student starting a dialog agent, chatting for a bit, then idling.
//             * You kind of need two data points to judge how long someone spent in this case:
//             *
//             * updated_at - started_at = total time spent in this response
//             *
//             * Once you've met the duration allotted you should be forced to view your response in a readonly way.
//             */
//            if (null === $authoritativeResponse) {
//                return [
//                    'label' => self::LABEL_VIEW,
//                    'responseId' => null,
//                    'enabled' => false,
//                ];
//            } else {
//                return [
//                    'label' => self::LABEL_VIEW,
//                    'responseId' => $authoritativeResponse->getId(),
//                    'enabled' => false,
//                ];
//            }
//        }


        /* Otherwise it's reached its open date, but it's not closed yet; deal with responses somewhat intricately
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
        }

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
