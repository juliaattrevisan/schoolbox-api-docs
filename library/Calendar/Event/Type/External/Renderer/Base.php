<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\External\Renderer;

use \Calendar\Event\Type\RendererBase;
use Calendar\Event\Type\External;
use Schoolbox\User;
use SitePage;

abstract class Base extends RendererBase
{

    /** @var External\Event */
    private $event;


    /**
     * @param SitePage       $page
     * @param External\Event $event
     * @param User           $user
     * @param User           $viewer
     */
    public function __construct(SitePage $page, External\Event $event, User $user, User $viewer)
    {
        parent::__construct($page, $user, $viewer);
        $this->event = $event;
    }


    /**
     * @return External\Event
     */
    protected function getEvent(): External\Event
    {
        return $this->event;
    }
}
