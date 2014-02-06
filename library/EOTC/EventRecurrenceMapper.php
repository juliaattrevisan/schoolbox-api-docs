<?php
/**
 * EventRecurrenceMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventRecurrenceMapper extends Calendar\EventRecurrenceMapper {

	/**
	 * After saving an event, do this
	 *
	 * @param Event $event
	 */
	protected function saveHook(Event $event) {
		$eventMetadataMapper = new EventMetadataMapper();
		$eventMetadataMapper->save($event->getMetadata());
		$eventMetadataMapper->saveRelation($event);
	}
}