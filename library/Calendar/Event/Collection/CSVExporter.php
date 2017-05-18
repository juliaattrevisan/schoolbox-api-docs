<?php
/**
 * Collection_Exporter
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 */

namespace Calendar\Event\Collection;

use Html2Text\Html2Text;
use Sabre\VObject\Component\VCalendar;
use Schoolbox\User;


class CSVExporter extends Renderer {

    var $csvFormat = array( 'start_date',
                            'start_time',
                            'finish_date',
                            'finish_time',
                            'all_day',
                            'name',
                            'detail',
                            'location',
                            'event_type',
                            'publish_date',
                            'attendance',
                           );

    /**
     * @var VCalendar $vCalendar
     */
    protected $vCalendar;

    /**
     * @var Html2Text
     */
    protected $textConverter;

    /**
     * This will stream the vcal output as it parses over the event collection
     */
    public function renderAll($type = null) {
		// Output base calendar, and output its start and end separately
		echo implode(',',$this->csvFormat)."\n";

		foreach ($this->getAllEvents() as $currentEvent) {
            echo $this->renderEvent($currentEvent, 'csv');
		}
	}

    /**
     * @param VCalendar $vCalendar
     */
    public function setVCalendar($vCalendar)
    {
        $this->vCalendar = $vCalendar;
    }

    /**
     * @return VCalendar
     */
    public function getVCalendar()
    {
        return $this->vCalendar;
    }

    /**
     * @param Html2Text $textConverter
     */
    public function setTextConverter($textConverter)
    {
        $this->textConverter = $textConverter;
    }

    /**
     * @return Html2Text
     */
    public function getTextConverter()
    {
        return $this->textConverter;
    }



}
