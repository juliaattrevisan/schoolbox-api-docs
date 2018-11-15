<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * @param \Group         $groupHandler
     * @param \Calendar      $calHandler
     * @param User           $viewer
     */
    public function __construct(SitePage $page, External\Event $event, User $user, \Group $groupHandler, \Calendar $calHandler, User $viewer)
    {
        parent::__construct($page, $user, $groupHandler, $calHandler, $viewer);
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
