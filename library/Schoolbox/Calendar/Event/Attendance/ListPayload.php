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
 * Class ListPayload
 *
 * Generate a list of people invited to attend $event
 * Also calculate a count of how many places are remaining
 *
 * Place any other meaningful metadata here about attendances
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class ListPayload
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
     * ListPayload constructor.
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

        /* Number of available places
         * Could be null, 0, or many
         */
        $available = null;

        if (null !== $event->getMaxAttendees()) {
            $available = $event->getMaxAttendees() - \count($guests);

            if ($available < 0) {
                $available = 0;
            }
        }

        /* Other counts
         */
        $pending = \count(array_filter($guests, static function($element) {
            return $element['status'] === Model::STATUS_PENDING;
        }));

        $accepted = \count(array_filter($guests, static function($element) {
            return $element['status'] === Model::STATUS_ACCEPTED;
        }));

        $declined = \count(array_filter($guests, static function($element) {
            return $element['status'] === Model::STATUS_DECLINED;
        }));

        return [

            /* The current user
             */
            'user' => $user,

            /* The number of empty spots that can be filled
             */
            'available' => $available,

            /* A list of invited users index by their user id
             */
            'guests' => $guests,

            /* Whether the current user is currently invited
             * i.e. currently on the attendance list
             */
            'isInvited' => isset($guests[$user->getId()]),

            /* Whether the current user can modify the event
             */
            'canModify' => $canModify,

            /* html chunk containing a list of invited users and their associated buttons
             * and icons representing their current rsvp state
             */
            'fragment' => $this->di
                ->get('webOutput:nochrome')
                ->setTemplate('@calendarEventAttendance/current.twig')
                ->setData([
                    'counts' => [
                        'pending' => $pending,
                        'accepted' => $accepted,
                        'declined' => $declined
                    ],
                    'canModify' => $canModify,
                    'event' => $event,
                    'guests' => $guests,
                    'status' => Model::CLASSES
                ])->getAsString()
        ];
    }
}
