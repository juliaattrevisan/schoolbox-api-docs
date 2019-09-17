<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Calendar\Api\ModuleChecker;
use Calendar_Api_Config_Mapper;
use Schoolbox\Calendar\Config\Campus;
use Schoolbox\Calendar\Config\EventType;
use Schoolbox\Calendar\Config\Filter;
use Schoolbox\Calendar\Config\SourceList;
use Schoolbox\Calendar\Config\Group;
use Schoolbox\Calendar\Util\ViewNameMapping;
use Schoolbox\CampusMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * @var CampusMapper
     */
    private $campusMapper;

    /**
     * @var EventTypeMapper
     */
    private $eventTypeMapper;

    /**
     * @var Calendar_Api_Config_Mapper
     */
    private $calApiConfigMapper;

    /**
     * @var ModuleChecker
     */
    private $moduleChecker;

    /**
     * @var EventMapper
     */
    private $eventMapper;

    /**
     * Controller constructor.
     * @param FolderMapper               $folderMapper
     * @param CampusMapper               $campusMapper
     * @param EventTypeMapper            $eventTypeMapper
     * @param Calendar_Api_Config_Mapper $calApiConfigMapper
     * @param ModuleChecker              $moduleChecker
     * @param EventMapper                $eventMapper
     */
    public function __construct(
        FolderMapper $folderMapper,
        CampusMapper $campusMapper,
        EventTypeMapper $eventTypeMapper,
        Calendar_Api_Config_Mapper $calApiConfigMapper,
        ModuleChecker $moduleChecker,
        EventMapper $eventMapper
    ) {
        $this->folderMapper         = $folderMapper;
        $this->campusMapper         = $campusMapper;
        $this->eventTypeMapper      = $eventTypeMapper;
        $this->calApiConfigMapper   = $calApiConfigMapper;
        $this->moduleChecker        = $moduleChecker;
        $this->eventMapper          = $eventMapper;
    }

    /**
     * @param string|null $selectedDate
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getOwnCalendar(?string $selectedDate): void
    {
        $this->buildResponse($this->getUser(), $this->generateSelectedDate($selectedDate));
    }

    /**
     * @param string|null $selectedDate
     * @param User        $user
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getCalendar(?string $selectedDate, User $user): void
    {
        $this->buildResponse($user, $this->generateSelectedDate($selectedDate));
    }

    /**
     * Redirect old paths to new
     * E.g calendar/week -> calendar or calendar/week/2013-08-2019/230 -> calendar/2013-08-2019/230
     *
     * @param string|null $selectedDate
     * @param User        $user
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function redirect(?string $selectedDate, User $user): void
    {
        if ($this->getRouter()->getMatchedRoute()->params['userId']) {
            $route = $this->getRouter()->generate(
                'calendar.getCalendar',
                [
                    'selectedDate'  => $selectedDate,
                    'userId'        => $user->getId()
                ]
            );
        } else {
            $route = $this->getRouter()->generate(
                'calendar.getOwnCalendar'
            );
        }

        if ($this->getRequest()->query->get('s')) {
            $route .= '?s=1';
        }

        $this->response->redirect->to($route);
    }

    /**
     * @param User        $user
     * @param string|null $selectedDate
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function buildResponse(User $user, ?string $selectedDate): void
    {
        $groups           = (new Group($user, $this->folderMapper))->generate();
        $campus           = (new Campus($user, $this->campusMapper))->generate();
        $eventType        = (new EventType($this->eventTypeMapper))->generate();
        $sourceList       = (new SourceList($this->calApiConfigMapper, $this->moduleChecker))->generate();
        $filters          = (new Filter($this->getSession()))->generate($campus->getData(), $groups->getData(), $eventType->getData(), $sourceList->getData());

        $calendarView = ViewNameMapping::MAP_STORED_TO_FC[$this->getUser()->getCalendarView()]
            ?? ViewNameMapping::VIEW_MONTH_FC_NAME;

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@calendar/calendar.twig',
                [
                    'user'          => $user->getId() === null ? $this->getUser() : $user,
                    'taskTypeId'    => $eventType->getTaskIdFromEvents(),
                    'groups'        => $groups,
                    'sources'       => $sourceList,
                    'campuses'      => $campus,
                    'eventTypes'    => $eventType,
                    'calendarView'  => $calendarView,
                    'filters'       => $filters,
                    'selectedDate'  => $selectedDate ?? Alaress_Date_Format::now()
                ]
            )
        );
    }

    /**
     * @param string|null $selectedDate
     * @return string|null
     * @throws \Schoolbox\Mapper\Exception
     */
    private function generateSelectedDate(?string $selectedDate): ?string
    {
        $series = $this->getRequest()->query->get('s');

        if ($series !== null) {
            $event = $this->eventMapper->findNextInSeries($series);

            return $event->getStartDateTime()->getURLDate();
        }

        return $selectedDate;
    }
}
