<?php
/**
 * Collection_Exporter
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 */

namespace Calendar\Event\Collection;

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
     * This will stream the vcal output as it parses over the event collection
     */
    public function renderAll($type = null) {
		// Output base calendar, and output its start and end separately
		echo implode(',',$this->csvFormat)."\n";

		foreach ($this->getAllEvents() as $currentEvent) {
            echo $this->renderEvent($currentEvent, 'csv');
		}
	}
}
