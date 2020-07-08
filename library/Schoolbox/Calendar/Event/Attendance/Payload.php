<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Aura\Di\Container;
use Aura\Di\Exception\ServiceNotFound;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Calendar\Event;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Class Payload
 *
 * Generate a list of people invited to attend $event
 * Also calculate a count of how many places are remaining
 *
 * Place any other meaningful metadata here about attendances
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class Payload
{

    /**
     * @var Container
     */
    private $di;


    /**
     * @var Mapper
     */
    private $attendanceMapper;


    /**
     * Payload constructor.
     *
     * @param Container $di
     * @param Mapper    $attendanceMapper
     */
    public function __construct(Container $di, Mapper $attendanceMapper)
    {
        $this->di = $di;
        $this->attendanceMapper = $attendanceMapper;
    }


    /**
     * Generate attendance payload info for $event
     *
     * @param Event $event
     * @param User $user
     * @return array
     * @throws ServiceNotFound
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function generate(Event $event, User $user): array
    {

        /* Whether $user has modify capability
         */
        $canModify = (new Event\Access(Access::getInstance()))->checkExisting($user, $event);

        /* Current list of attendees for $event
         */
        $guests = $this->attendanceMapper->findAttendances($event);

        /* Do some counts and analysis on the $guest data
         */
        $analysis = new PayloadAnalysis($event->getMaxAttendees(), $guests);

        return [

            /* The current user
             */
            'user' => $user,

            /* Convenient counts
             */
            'attendees' => [

                /* A list of invited users index by their user id
                 */
                'guests' => $guests,

                /* Maximum number of people you can invite
                 */
                'max' => $event->getMaxAttendees(),

                /* Number of people currently invited who have, or could, Accept their invitation
                 */
                'num' => $analysis->getNumAttendees(),

                /* The number of empty spots that can be filled
                 */
                'available' => $analysis->getNumAvailable(),

                /* Misc details
                 */
                'pending' => $analysis->getNumPending(),
                'accepted' => $analysis->getNumAccepted(),
                'declined' => $analysis->getNumDeclined(),
            ],

            /* Current attendance level
             */
            'level' => $event->getAttendance(),

            /* Whether the current user can modify the event
             */
            'canModify' => $canModify,
        ];
    }
}
