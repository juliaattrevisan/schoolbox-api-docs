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
     * Controller constructor.
     * @param FolderMapper                $folderMapper
     * @param CampusMapper                $campusMapper
     * @param EventTypeMapper             $eventTypeMapper
     * @param Calendar_Api_Config_Mapper $calApiConfigMapper
     */
    public function __construct(FolderMapper $folderMapper, CampusMapper $campusMapper, EventTypeMapper $eventTypeMapper, Calendar_Api_Config_Mapper $calApiConfigMapper, ModuleChecker $moduleChecker)
    {
        $this->folderMapper         = $folderMapper;
        $this->campusMapper         = $campusMapper;
        $this->eventTypeMapper      = $eventTypeMapper;
        $this->calApiConfigMapper   = $calApiConfigMapper;
        $this->moduleChecker        = $moduleChecker;
    }

    /**
     * @param string|null $selectedDate
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getCalendar(?string $selectedDate): void
    {
        $groups           = (new Group($this->getUser(), $this->folderMapper))->generate();
        $campus           = (new Campus($this->getUser(), $this->campusMapper))->generate();
        $eventType        = (new EventType($this->eventTypeMapper))->generate();
        $sourceList       = (new SourceList($this->calApiConfigMapper, $this->moduleChecker))->generate();
        $filters          = (new Filter($this->getSession()))->generate($campus->getData(), $groups->getData(), $eventType->getData(), $sourceList->getData());

        $calendarView = ViewNameMapping::MAP_STORED_TO_FC[$this->getUser()->getCalendarView()]
            ?? ViewNameMapping::VIEW_MONTH_FC_NAME;

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@calendar/calendar.twig',
                [
                    'user'          => $this->getUser(),
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
}
