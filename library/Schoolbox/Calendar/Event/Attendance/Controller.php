<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{

    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * @var EventMapper
     */
    private $eventMapper;


    /**
     * @var Mapper
     */
    private $attendanceMapper;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param EventMapper $eventMapper
     */
    public function __construct(ErrorMapper $errorMapper, EventMapper $eventMapper, Mapper $attendanceMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->eventMapper = $eventMapper;
        $this->attendanceMapper = $attendanceMapper;

    }


    /**
     * Provide form
     *
     * @param Event $event
     * @throws ServiceNotFound
     */
    public function getCurrent(Event $event): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEventAttendance/index.twig', [
            'event' => $event,
        ])->setTitle('Example'));
    }


    /**
     * Process the form vars
     */
    public function postModify(): void
    {
        new \Alaress_Probe($this->request->post['guests'], $this->request->post['existingGuests']);
    }
}
