<?php
/**
 * Builder for calendar APIs for a model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\ResourceBooking\Report;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Calendar_Api_Composite;
use Calendar_Api_EventType_Interface;
use External_TimetableInterface;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\User;

class CalendarApiFactory extends BaseModule
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @var External_TimetableInterface
     */
    protected $timetable;

    /**
     * Constructor
     *
     * @param User                        $user
     * @param External_TimetableInterface $timetable
     */
    public function __construct(User $user, External_TimetableInterface $timetable)
    {
        $this
            ->setUser($user)
            ->setTimetable($timetable)
        ;
    }

    /**
     * Creates a calendar API for this resource item.
     *
     * @param  Item\Model $resource
     * @return Calendar_Api_EventType_Interface
     */
    public function build(Item\Model $resource)
    {
        $api = new Calendar_Api_Composite();

        // Add the resource API
        $resourceApi = new \Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
            $this->getUser(),
            array()
        );
        $resourceApi->setResourceId($resource->getId());
        $api->add($resourceApi);

        // Add the room code API if this resource has got a room code
        if ($roomCode = $resource->getRoomCode()) {
            $timetableApi = new \Calendar_Api_EventType_Timetable_Room(
                $this->getUser(),
                array()
            );
            $timetableApi->setRoomCode($roomCode)->setExternalTimetable($this->getTimetable());
            $api->add($timetableApi);
        }
        return $api;
    }

    /**
     * @return User
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * @param  User $user
     * @return $this
     */
    protected function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return External_TimetableInterface
     */
    protected function getTimetable()
    {
        return $this->timetable;
    }

    /**
     * @param  External_TimetableInterface $timetable
     * @return $this
     */
    protected function setTimetable($timetable)
    {
        $this->timetable = $timetable;
        return $this;
    }
}
