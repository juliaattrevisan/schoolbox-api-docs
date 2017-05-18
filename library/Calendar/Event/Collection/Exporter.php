<?php
/**
 * Collection_Exporter
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 */

namespace Calendar\Event\Collection;

use Sabre\VObject\Component\VCalendar;
use Alaress\Html\ToText as Html2Text;
use Schoolbox\User;
use SitePage;
use Config;

class Exporter extends Renderer {

    /**
     * @var VCalendar
     */
    var $vCalendar;

    /**
     * @var Html2Text
     */
    protected $textConverter;

    /**
     * Constructor
     *
     * @param $api
     * @param $startTimestamp
     * @param $endTimestamp
     * @param $user
     * @param $page
     * @param $viewer
     */
    public function __construct($api, $startTimestamp, $endTimestamp, User $user, \SitePage $page, User $viewer)
    {
        parent::__construct($api, $startTimestamp, $endTimestamp, $user, $page, $viewer);

		$this->setVCalendar(new VCalendar());
		$this->getVCalendar()->add('method', 'PUBLISH');
        $this->getVCalendar()->add('X-WR-CALNAME', Config::get('title').' Calendar');
        $this->getVCalendar()->add('X-WR-CALDESC', 'Calendar for '.Config::get('school_name'));

        $this->setTextConverter(new Html2Text());
	}

    /**
     * This will stream the vcal output as it parses over the event collection
     */
    public function renderAll($type = null) {
		// Output base calendar, and output its start and end separately
		echo trim(str_replace('END:VCALENDAR', '', $this->getVCalendar()->serialize()))."\r\n";

		foreach ($this->getAllEvents() as $currentEvent) {
            echo $this->renderEvent($currentEvent, 'vcal');
		}

        echo "END:VCALENDAR";
	}

    /**
     * This overrides the default in order to inject the vCalendar object into the renderer, which is obviously
     *
     * @param $event
     * @param $type
     * @return mixed
     */
    public function renderEvent($event, $type)
    {
        $rendererClass = $event->getRendererClass($type);
        $renderObj = new $rendererClass(
            $this->getPage(),
            $event,
            $this->getUser(),
            $this->getGroupHandler(),
            $this->getCalendarHandler(),
            $this->getViewer()
        );
        $renderObj->setVCalendar($this->getVCalendar());
        $renderObj->setTextConverter($this->getTextConverter());
        return $renderObj->render();
    }

    /**
     * @param \Sabre\VObject\Component\VCalendar $vCalendar
     */
    public function setVCalendar($vCalendar)
    {
        $this->vCalendar = $vCalendar;
    }

    /**
     * @return \Sabre\VObject\Component\VCalendar
     */
    public function getVCalendar()
    {
        return $this->vCalendar;
    }

    /**
     * @param \Html2Text\Html2Text $textConverter
     */
    public function setTextConverter($textConverter)
    {
        $this->textConverter = $textConverter;
    }

    /**
     * @return \Html2Text\Html2Text
     */
    public function getTextConverter()
    {
        return $this->textConverter;
    }



}
