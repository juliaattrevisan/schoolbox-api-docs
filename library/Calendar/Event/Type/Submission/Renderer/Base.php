<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type\Submission\Renderer;

use \Calendar\Event\Type\RendererBase;
use Schoolbox\User;
use SitePage;
use Calendar\Event\Type\Submission;

abstract class Base extends RendererBase
{

    /** @var Submission\Event */
    private $event;


    /**
     * @param SitePage         $page
     * @param Submission\Event $event
     * @param User             $user
     * @param \Group           $groupHandler
     * @param \Calendar        $calHandler
     * @param User             $viewer
     */
    public function __construct(SitePage $page, Submission\Event $event, User $user, \Group $groupHandler, \Calendar $calHandler, User $viewer)
    {
        parent::__construct($page, $user, $groupHandler, $calHandler, $viewer);
        $this->event = $event;
    }


    /**
     * Helper function to determine if person viewing assessment event can edit it, this should probably be on the
     * event itself but its not so this will help
     *
     * @return bool
     */
    public function isEditable()
    {
        return $this->getViewer()->getRole()->isStaff();
    }


    /**
     * @return Submission\Event
     */
    protected function getEvent(): Submission\Event
    {
        return $this->event;
    }
} 
