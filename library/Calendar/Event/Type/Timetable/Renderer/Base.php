<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Calendar\Event\Type\Timetable\Renderer;

use Aura\Di\ContainerInterface;
use Calendar\Event\Type\RendererBase;
use Calendar\Event\Type\Timetable;
use Calendar\Event\Type\Timetable\Event;
use Schoolbox\Config\Loader as Config;
use Schoolbox\User;

abstract class Base extends RendererBase
{

    /** @var Timetable\Event */
    private $event;


    /**
     * Constructor
     *
     * @param ContainerInterface $di
     * @param Event              $event
     * @param User               $user
     * @param User               $viewer
     */
    public function __construct(ContainerInterface $di, Timetable\Event $event, User $user, User $viewer)
    {
        parent::__construct($di, $user, $viewer);
        $this->event = $event;
    }


    /**
     * Helper function to control showing the attendance link
     */
    public function showAttendanceLink() {

        $config = new Config();

        if (
            $this->getEvent() instanceof Event
            && $config->get('external_type') == 'synergetic'
            && $config->get('external_attendance')
            && $this->getEvent()->hasAttendance()
            && $this->getViewer()->getRole()->isStaff()
            && $this->getEvent()->getCode() != ''
            && count($this->getEvent()->getAttendancePeriods())
        )
        {
            $now = new \Alaress_Date_Format();
            $now->endOfDay();

            $from = new \Alaress_Date_Format();
            $from->startOfDay();
            $from->addDays($config->get('external_attendance_days') * -1);

            if($this->getEvent()->getStartTime()->gte($from) && $this->getEvent()->getStartTime()->lte($now)) {
                return true;
            }
        }
        return false;
    }


    /**
     * @return Timetable\Event
     */
    protected function getEvent(): Timetable\Event
    {
        return $this->event;
    }
}
