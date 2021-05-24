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
     * @param User  $user
     * @return array
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ServiceNotFound
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

        /* Post-analysis censorship
         *
         * After the basic Payload administration has occurred, which generates counts and perms etc.
         * check whether $user has been invited into an event with a hidden guest list
         *
         * If so, remove everyone except $user from the guest list
         */
        if (!$canModify && !$event->getShowAttendees()) {
            $guests = array_values(array_filter($guests, static function ($element) use ($user) {
                return $element['id'] === $user->getId();
            }));
        }

        /* Decide whether $user can invite other users
         *
         * A person who can create an event might not have the ability to invite attendees
         * so it's not equivalent to $canModify
         */
        $canInvite = (new ValidCanInvite())->isSatisfiedBy($user, (bool)$this->di->get('config')->get('calendar_student_can_invite'));

        return [
            /* Current attendance level for the event
             */
            'level' => $event->getAttendance(),

            /* Details about the current user
             */
            'currentUser' => [

                /* The current user
                 */
                'user' => $user,

                /* The current user RSVP status
                 */
                'status' => $analysis->getUserStatus($user),

                /* Whether the current user can modify the event
                 */
                'canModify' => $canModify,

                /* Whether the current user can invite other users to an event
                 */
                'canInvite' => $canInvite
            ],

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
        ];
    }
}
