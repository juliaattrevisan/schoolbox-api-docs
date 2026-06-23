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
     *
     *
     * @param Event\Model $event
     * @return void
     */
    public function postDismissEvent(Event\Model $event): void
    {
        // TODO
    }


    /**
     *
     *
     * @param Event\Model $event
     * @return void
     */
    public function postConfirmEvent(Event\Model $event): void
    {
        // TODO
    }


    /**
     *
     *
     * @param Event\Model $event
     * @return void
     */
    public function postResolveEvent(Event\Model $event): void
    {
        // TODO
    }

}
