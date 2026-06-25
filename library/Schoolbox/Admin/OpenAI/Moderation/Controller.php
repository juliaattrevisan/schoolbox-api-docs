<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Moderation;

use Alaress\Web\Controller\Base;
use Schoolbox\OpenAi\Moderation\Event;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param Event\Mapper $eventMapper
     */
    public function __construct(
        private Event\Mapper $eventMapper,
    ) {
    }


    /**
     * Transition the event to NO_ACTION_REQUIRED, recording the reviewing staff member.
     *
     * (The staff member has reviewed the event and deemed that no further action is required.)
     *
     * @param Event\Model $event
     * @return void
     */
    public function postDismissEvent(Event\Model $event): void
    {
        $event
            ->setStatus(Event\Status::NO_ACTION_REQUIRED)
            ->setReviewedBy($this->getUser())
            ->setReviewedAt(\Alaress_Date_Format::now());

        $this->eventMapper->save($event);

        $this->respondWithEvent($event);
    }


    /**
     * Transition the event to FURTHER_ACTION_REQUIRED, recording the reviewing staff member.
     *
     * (The staff member has reviewed the event and deemed that further action is required, but
     * that action has not yet been taken.) Any prior resolution is cleared, as the event is no
     * longer considered resolved.
     *
     * @param Event\Model $event
     * @return void
     */
    public function postConfirmEvent(Event\Model $event): void
    {
        $event
            ->setStatus(Event\Status::FURTHER_ACTION_REQUIRED)
            ->setReviewedBy($this->getUser())
            ->setReviewedAt(\Alaress_Date_Format::now())
            ->setResolvedBy(null)
            ->setResolvedAt(null);

        $this->eventMapper->save($event);

        $this->respondWithEvent($event);
    }


    /**
     * Transition the event to FURTHER_ACTION_TAKEN, recording the resolving staff member.
     *
     * (The staff member has resolved the 'further action required' request.) The earlier review
     * details are preserved.
     *
     * @param Event\Model $event
     * @return void
     */
    public function postResolveEvent(Event\Model $event): void
    {
        $event
            ->setStatus(Event\Status::FURTHER_ACTION_TAKEN)
            ->setResolvedBy($this->getUser())
            ->setResolvedAt(\Alaress_Date_Format::now());

        $this->eventMapper->save($event);

        $this->respondWithEvent($event);
    }


    /**
     * Respond as JSON with the translated event, so the frontend can update its data with the
     * event's new state.
     *
     * @param Event\Model $event
     * @return void
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/moderationEvent.ts AiModerationEvent
     */
    private function respondWithEvent(Event\Model $event): void
    {
        $userTranslator = new User\Translator\Author\ModelToArray();
        $linksTranslator = new Event\Translator\Links(
            $this->routeGenerator,
            $this->getAuthorizer(),
            $this->getUser(),
        );
        $eventTranslator = new Event\Translator\ModelToArray($userTranslator, $linksTranslator);

        $this->response = $this->responseAsJson([
            'event' => $eventTranslator->__invoke($event),
        ]);
    }
}
