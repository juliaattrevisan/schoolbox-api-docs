<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

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
     *
     * @param Generator $routeGenerator
     * @param Authorizer $authorizer
     */
    public function __construct(
        private \Aura\Router\Generator $routeGenerator,
        private Authorizer $authorizer,
    ) {
    }


    /**
     * Takes a fully-hydrated $participant along with their $response instances and the assessment.
     * From those, decides what the call to action should be on the Summary screen.
     *
     * @param Model $assessment
     * @param null|Participant $participant
     * @param User $viewer
     * @return array|null
     * @throws RouteNotFound
     */
    public function generate(Model $assessment, ?Participant $participant, \Schoolbox\User $viewer): ?array
    {
        /* First check the View Response and Create Response routes to see if there's at least one button that
         * COULD potentially display here, then decide whether it's enabled or not.
         */
        $button = $this->findViewResponseButton($assessment, $participant, $viewer);

        if ($button) {
            return $button;
        }

        /* You can't view a response; maybe you can create one
         */
        $button = $this->findCreateResponseButton($assessment, $participant, $viewer);

        if ($button) {
            return $button;
        }

        /* The $viewer couldn't access either routes that would have fulfilled a call to action meaningfully:
         * The buttons that *could* display from here are disabled.
         *
         * Note that assessments must be an implementation. The canonical getCreateResponseForm() auth check allows
         * you (seemingly) to bypass this requirement. There's a deep heuristic there that maybe shines a light on
         * other swampy ideas...
         */

        /* Students would see a disabled Begin button under the premise they'd be able to make a response someday
         */
        if ($viewer->getRole()->isStudent() && $assessment->isImplementation()) {
            return [
                'label' => self::LABEL_START,
                'url' => null,
            ];
        }

        /* Staff members end up seeing a View Discussion button here when they're viewing a dialog agent's summary
         * screen _for themselves_ which is maybe a bit weird...perhaps it should just hide the call to action.
         * Staff aren't ever meant to do it for themselves.
         */
        if ($viewer->getRole()->isStaff() && $assessment->isImplementation()) {
            return [
                'label' => self::LABEL_VIEW,
                'url' => null,
            ];
        }

        /* Parents don't have a button at all.
         * Guests don't have a button at all.
         *     These two roletypes should have been precluded in Module so you should never really get here except
         *     if you're trying to unlock something
         *
         * Staff don't have a button if you're looking at a specification assessment.
         */
        return null;
    }


    /**
     * See if you can get to the Create Response screen
     *
     * @param Model $assessment
     * @param Participant|null $participant
     * @param User $viewer
     * @return array|null
     * @throws RouteNotFound
     */
    private function findCreateResponseButton(Model $assessment, ?Participant $participant, \Schoolbox\User $viewer): ?array
    {
        if (null === $participant) {
            return null;
        }

        $route = 'learningAssessment.getCreateResponseForm';

        $params = [
            'assessment' => $assessment,
            'assessmentId' => $assessment->getId(),
            'student' => $participant->getUser(),
            'studentId' => $participant->getUser()->getId(),
        ];

        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
            $url = $this->routeGenerator->generate($route, $params);

            /* See if there's an authoritative response to resume; if not, we're gonna create a new one
             */
            $authoritativeResponse = $participant->getAuthoritativeResponse(false);

            /* TODO incorporate Assessment Duration
             *
             * Note that durations are -per response- and mostly enforced in frontend.
             * Backend implements a crontab that forcibly shuts whatever the frontend couldn't.
             * Durations don't factor into whether you can make responses or anything, it's a clerical duty.
             */

            /* Authoritative response exists and it's not yet submitted; resume it
             */
            if ($authoritativeResponse && !$authoritativeResponse->isSubmitted()) {
                return [
                    'label' => self::LABEL_RESUME,
                    'url' => $url,
                ];
            }

            /* Otherwise you're creating a new thing
             */
            return [
                'label' => self::LABEL_START,
                'url' => $url,
            ];
        }

        /* You can't create a response for any one of many reasons:
         * e.g. not open yet, due in  archived academic period, etc.
         */
        return null;
    }


    /**
     * See if you can get to the View Response url
     *
     * @param Model $assessment
     * @param Participant|null $participant
     * @param User $viewer
     * @return array|null
     * @throws RouteNotFound
     */
    private function findViewResponseButton(Model $assessment, ?Participant $participant, \Schoolbox\User $viewer): ?array
    {
        if (null === $participant) {
            return null;
        }

        /* If there's an authoritative response, you can at least view it; whether or not it's in a "published"
         * state doesn't really matter yet
         */
        $authoritativeResponse = $participant->getAuthoritativeResponse(false);

        if (null === $authoritativeResponse) {
            return null;
        }

        /* If the assessment has surpassed its due date,
         * but this is an authoritative response,
         * button says "View" but lets you continue so you can look at it.
         */
        $route = 'learningDialogAgent.getViewResponse';

        $params = [
            'assessment' => $assessment,
            'assessmentId' => $assessment->getId(),
            'student' => $participant->getUser(),
            'studentId' => $participant->getUser()->getId(),
            'response' => $authoritativeResponse,
            'responseId' => $authoritativeResponse->getId()
        ];

        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
            $url = $this->routeGenerator->generate($route, $params);

            return [
                'label' => self::LABEL_VIEW,
                'url' => $url,
            ];
        }

        return null;
    }
}
