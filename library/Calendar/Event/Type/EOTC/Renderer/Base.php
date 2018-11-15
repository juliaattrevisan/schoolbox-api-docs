<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\EOTC\Renderer;

use Calendar\Event\Type\RendererBase;
use Calendar\Event\Type\EOTC;
use Schoolbox\User;
use SitePage;


abstract class Base extends RendererBase
{
    /** @var EOTC\Event */
    private $event;

    /**
     * @param SitePage   $page
     * @param EOTC\Event $event
     * @param User       $user
     * @param \Group     $groupHandler
     * @param \Calendar  $calHandler
     * @param User       $viewer
     */
    public function __construct(SitePage $page, EOTC\Event $event, User $user, \Group $groupHandler, \Calendar $calHandler, User $viewer)
    {
        parent::__construct($page, $user, $groupHandler, $calHandler, $viewer);
        $this->event = $event;
    }


    /**
     * @return EOTC\Event
     */
    protected function getEvent(): EOTC\Event
    {
        return $this->event;
    }
}
