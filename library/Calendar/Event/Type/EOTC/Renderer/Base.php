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
use Schoolbox\Permission\Access\UserAccess;

abstract class Base extends RendererBase
{
    /**
     * If the Publish Date hasn't been reached yet, only show the events to Superusers, owners,
     * and those with explicit write permission so they can still edit/click them.
     * Otherwise, they don't appear at all for everyone else
     *
     * @return bool
     */
    public function isVisible() {
        $isFutureEvent = ($this->getEvent()->getPublishDate()->getUnixTimestamp() >= time());

        // Super || owner || writePermission
        $alwaysVisible =
            $this->getGroupHandler()->isSuperuser() ||
            $this->getEvent()->isOwner() ||
            is_numeric($this->getEvent()->getCategoryId()) && $this->getGroupHandler()->getPermission($this->getEvent()->getCategoryId()) >= UserAccess::ACCESS_WRITE;

        if ($isFutureEvent && !($alwaysVisible)) {
            return false;
        }

        return true;
    }
}