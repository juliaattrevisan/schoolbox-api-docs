<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Moderation;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\OpenAi\Moderation\Event;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminAiModeration';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only supers can get here
         */
        $isAdmin = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser(),
        );

        $preloadEvent = function (array $params) {
            return $this->preloadEvent($params);
        };

        $eventParam = '{eventId:\d+}';

        // TODO: next state transition rules

        /* Transition an AI Interaction Moderation event to the NO_ACTION_REQUIRED status.
         * (When a school staff member has reviewed and deemed that there is no further action required for this event)
         */
        $this->register('POST', "/event/{$eventParam}/dismiss", 'postDismissEvent', $isAdmin, $preloadEvent);

        /* Transition an AI Interaction Moderation event to the FURTHER_ACTION_REQUIRED status.
         * (When a school staff member has reviewed and deemed that further action is required, but that action has not yet been taken)
         */
        $this->register('POST', "/event/{$eventParam}/confirm", 'postConfirmEvent', $isAdmin, $preloadEvent);

        /* Transition an AI Interaction Moderation event to the FURTHER_ACTION_TAKEN status.
         * (When a school staff member resolves the 'further action required' request)
         */
        $this->register('POST', "/event/{$eventParam}/resolve", 'postResolveEvent', $isAdmin, $preloadEvent);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getModerationEventMapper(),
        );
    }


    /**
     * Preload the first log entry on the {sessionId} param
     *
     * @param array $params
     * @return array
     * @throws MapperException
     * @throws Deleted
     */
    private function preloadEvent(array $params): array
    {
        if ($params['event'] ?? null) {
            return ['event' => $params['event']];
        }

        if (!isset($params['eventId'])) {
            throw new InvalidArgumentException('AI moderation event identifier parameter is missing.');
        }

        $event = $this->getModerationEventMapper()->findById($params['eventId']);

        return ['event' => $event];
    }


    /**
     * @return Event\Mapper
     * @throws InvalidArgumentException If unalbe to find mapper class
     */
    protected function getModerationEventMapper(): Event\Mapper
    {
        return $this->getMapperFactory()->get(Event\Mapper::class);
    }
}
