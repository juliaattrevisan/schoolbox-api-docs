<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\Calendar\Config\Campus;
use Schoolbox\Calendar\Config\EventType;
use Schoolbox\Calendar\Config\Filter;
use Schoolbox\Calendar\Config\SourceList;
use Schoolbox\Calendar\Config\Group;
use Schoolbox\Calendar\Util\ViewNameMapping;
use Schoolbox\CampusMapper;
use Schoolbox\Config\ConfigMapper;
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
     * Controller constructor.
     * @param FolderMapper $folderMapper
     * @param CampusMapper $campusMapper
     */
    public function __construct(FolderMapper $folderMapper, CampusMapper $campusMapper)
    {
        $this->folderMapper     = $folderMapper;
        $this->campusMapper     = $campusMapper;
    }

    /**
     * @param string|null $selectedDate
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getCalendar(?string $selectedDate): void
    {
        $groups           = (new Group($this->getUser(), $this->folderMapper))->generate();
        $campus           = (new Campus($this->getUser(), $this->campusMapper))->generate();
        $eventType        = (new EventType())->generate();
        $sourceList       = (new SourceList(ConfigMapper::getInstance()))->generate();
        $filters          = (new Filter($this->di))->generate($campus->getData(), $groups->getData(), $eventType->getData(), $sourceList->getData());

        $calendarView = ViewNameMapping::MAP_STORED_TO_FC[$this->getUser()->getCalendarView()]
            ?? ViewNameMapping::VIEW_MONTH_FC_NAME;

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@calendar/calendar.twig',
                [
                    'user'          => $this->getUser(),
                    'taskTypeId'    => $eventType->getTaskIdFromEvents(),
                    'groups'        => $groups->toJson(),
                    'sources'       => $sourceList->toJson(),
                    'campuses'      => $campus->toJson(),
                    'eventTypes'    => $eventType->toJson(),
                    'calendarView'  => $calendarView,
                    'filters'       => $filters->toJson(),
                    'selectedDate'  => $selectedDate ?? Alaress_Date_Format::now()
                ]
            )
        );
    }
}
