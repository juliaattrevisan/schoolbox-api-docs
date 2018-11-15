<?php
/**
 * Collection_Exporter
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 */

namespace Calendar\Event\Collection;

use Calendar\Event\Render\VObjectTrait;
use Sabre\VObject\Component\VCalendar;
use Alaress\Html\ToText as Html2Text;
use Schoolbox\User;
use Config;

class VCalExporter extends Renderer {

    /**
     * @var VCalendar
     */
    private $vCalendar;

    /**
     * @var Html2Text
     */
    private $textConverter;

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

		$this->vCalendar = new VCalendar();
		$this->vCalendar->add('method', 'PUBLISH');
        $this->vCalendar->add('X-WR-CALNAME', Config::get('title').' Calendar');
        $this->vCalendar->add('X-WR-CALDESC', 'Calendar for '.Config::get('school_name'));

        $this->textConverter = new Html2Text();
	}

    /**
     * This will stream the vcal output as it parses over the event collection
     */
    public function renderAll($format = null) {
		// Output base calendar, and output its start and end separately
		echo trim(str_replace('END:VCALENDAR', '', $this->vCalendar->serialize()))."\r\n";

		foreach ($this->getAllEvents() as $currentEvent) {
            echo $this->renderEvent($currentEvent, 'vcal');
		}

        echo "END:VCALENDAR";
	}

    /**
     * This overrides the default in order to inject the vCalendar object into the renderer, which is obviously
     *
     * @param $event
     * @param $format
     * @return string
     * @throws \Exception
     */
    public function renderEvent($event, $format)
    {
        $rendererClass = $event->getRendererClass($format);
        /** @var VObjectTrait $renderObj */
        $renderObj = new $rendererClass(
            $this->getPage(),
            $event,
            $this->getUser(),
            $this->getGroupHandler(),
            $this->getCalendarHandler(),
            $this->getViewer()
        );
        $renderObj->setVCalendar($this->vCalendar);
        $renderObj->setTextConverter($this->textConverter);
        return $renderObj->render();
    }
}
