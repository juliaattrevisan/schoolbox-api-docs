<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Aura\Di\Container;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Calendar\Event;

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
     * @return array
     * @throws ServiceNotFound
     */
    public function generate(Event $event): array
    {
        $guests = $this->attendanceMapper->findAttendances($event);

        $available = null;

        if (null !== $event->getMaxAttendees()) {
            $available = $event->getMaxAttendees() - \count($guests);

            if ($available < 0) {
                $available = 0;
            }
        }

        return [
            'available' => $available,
            'guests' => $guests,
            'fragment' => $this->di
                ->get('webOutput:nochrome')
                ->setTemplate('@calendarEventAttendance/current.twig')
                ->setData([
                    'event' => $event,
                    'guests' => $guests,
                ])->getAsString()
        ];
    }
}
