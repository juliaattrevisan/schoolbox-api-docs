<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type\Day\Renderer;

use \Calendar\Event\Type\RendererBase;

class Base extends RendererBase
{
    /**
     * If the Publish Date hasn't been reached yet, only show the events to Superusers
     * in a different way so they can still edit/click them. Otherwise, they don't appear
     * at all for everyone else
     *
     * @return bool
     */
    public function isVisible() {
        $isFutureEvent = ($this->getEvent()->getPublishDate()->getUnixTimestamp() >= time());
        $isVisibleToUser = ($this->getViewer()->isSuperuser() || $this->getEvent()->isOwner() || $this->getEvent()->isEditable());
        if ($isFutureEvent && !($isVisibleToUser)) {
            return false;
        }
        return true;
    }
} 