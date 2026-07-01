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
     * Conditions for when this transition can occur are handled by the module.
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
     * Conditions for when this transition can occur are handled by the module.
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
     * Conditions for when this transition can occur are handled by the module.
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
     * Respond with a JSON payload containing the translated $event including routes/status.
     * Used for asynchronous calls when the event state may have changed so that the frontend
     * can realign its data with the new state.
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
