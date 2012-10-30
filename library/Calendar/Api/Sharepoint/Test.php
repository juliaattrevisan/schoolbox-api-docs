<?php
/**
 * Calendar_Api_Sharepoint_Test
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Sharepoint_Test extends Calendar_Api_Sharepoint_Stk {

	/**
	 * Get day events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getDayEvents($currentDay) {
		$now = new Alaress_Date_Format($currentDay, 'U');

		$start = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
		$end = new Alaress_Date_Format('2012-10-22' . ' 23:59:59', 'y-m-d h:i:s');

		echo $start->getDatabase();
		echo "\n";
		echo $end->getDatabase();
		echo "\n";
		echo "\n";

		$startDate = $start->getDatabase();
		$endDate = $end->getDatabase();

		$xmlPayload = new Calendar_Api_Sharepoint_Stk_PayloadTest($startDate, $endDate);

		$response = $this->getSoap()->GetListItems(new SoapVar($xmlPayload->getPayload(), XSD_ANYXML));

		$allEvents = $xmlPayload->parseXml($response->GetListItemsResult->any);

		/* See what happened
		 */
		print_r($allEvents);
		die();
	}
}
