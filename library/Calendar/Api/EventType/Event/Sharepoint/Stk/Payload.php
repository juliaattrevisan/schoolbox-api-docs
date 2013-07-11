<?php
/**
 * Calendar_Api_Event_Sharepoint_Stk_Payload
 *
 * See http://www.threewill.com/2011/08/remote-sharepoint-calendars/ for more info
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Event_Sharepoint_Stk_Payload {

	/* Name or guid of the list
	 */
	private $listName = 'Master Calendar';
	private $rowLimit = 1000;

	/**
	 * @var DateTime
	 */
	private $startDate;

	/**
	 * @var DateTime
	 */
	private $endDate;

	/* Special dromedary
	 *
	 * Note: Sharepoint is sensitive to Value Types. <Value Type="Number">2</Value>
	 * is not the same as <Value Type="Text">2</Value>
	 *
	 * Note: More than two boolean operators must be combined in nested AND
	 * tags, similar to NAND gates in electronics eng.
	 */
	private $camlQuery = '
		<GetListItems xmlns="http://schemas.microsoft.com/sharepoint/soap/">
			<listName>%s</listName>
			<rowLimit>%s</rowLimit>
			<query>
				<Query xmlns="http://schemas.microsoft.com/sharepoint/soap/">
					<Where>
						<And>
							<DateRangesOverlap>
								<FieldRef Name="EventDate" />
								<FieldRef Name="EndDate" />
								<FieldRef Name="RecurrenceID" />
								<Value Type="DateTime">
									<%s />
								</Value>
							</DateRangesOverlap>
							<Eq>
								<FieldRef Name="_ModerationStatus"/>
								<Value Type="Number">0</Value>
							</Eq>
						</And>
					</Where>
					<OrderBy>
						<FieldRef Name="EventDate"/>
					</OrderBy>
				</Query>
			</query>
			<viewFields>
				<ViewFields xmlns="">
					<FieldRef Name="Title"/>
					<FieldRef Name="School"/>
					<FieldRef Name="ContentType"/>
					<FieldRef Name="_ModerationStatus"/>
					<FieldRef Name="EventDate"/>
					<FieldRef Name="EndDate"/>
					<FieldRef Name="Created"/>
					<FieldRef Name="fRecurrence"/>
					<FieldRef Name="fAllDayEvent"/>
					<FieldRef Name="Duration"/>
					<FieldRef Name="RecurrenceData"/>
					<FieldRef Name="UID"/>
					<FieldRef Name="EventType"/>
					<FieldRef Name="MasterSeriesItemID"/>
				</ViewFields>
			</viewFields>
			<queryOptions>
				<QueryOptions xmlns="">
					<CalendarDate>%s</CalendarDate>
					<ExpandRecurrence>TRUE</ExpandRecurrence>
					<IncludeMandatoryColumns>FALSE</IncludeMandatoryColumns>
					<ViewAttributes Scope="RecursiveAll" />
					<RecurrencePatternXMLVersion>v3</RecurrencePatternXMLVersion>
				</QueryOptions>
			</queryOptions>
		</GetListItems>
	';

	/* Final processed payload xml
	 */
	private $payload = '';

	/**
	 * Constructor
	 *
	 * @param DateTime $startDate
	 * @param DateTime $endDate
	 */
	public function __construct(DateTime $startDate, DateTime $endDate) {
		$this->setStartDate($startDate);
		$this->setEndDate($endDate);

		$this->generatePayload();
	}

	/**
	 * Generate final payload xml
	 *
	 * @return string
	 */
	public function generatePayload() {
		//
		// Mangle request date into something in the middle of start and end
		// Request month, year or day based on how many days are being requested
		$diff = $this->getStartDate()->diff($this->getEndDate());
		$days = floor($diff->days/2);
		$midpoint = clone $this->getStartDate();
		$midpoint->modify("+$days day");

		$payload = sprintf(
			$this->getCamlQuery(),
			$this->getListName(),
			$this->getRowLimit(),
			($days <= 1 ? 'Today' : 'Month'),
			$midpoint->format('Y-m-d')
		);
//		new Alaress_Probe($payload);

		$this->setPayload($payload);
	}

	/**
	 * Parse xml into object structure
	 *
	 * @param $xml
	 * @return SimpleXMLElement
	 */
	public function parseXml($xml) {
		$output = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml);

		return simplexml_load_string($output, 'SimpleXMLElement', LIBXML_NOCDATA);
	}

	/* Getters/Setters
	 */

	/**
	 * Get caml query
	 *
	 * @return string
	 */
	private function getCamlQuery() {
		return $this->camlQuery;
	}

	/**
	 * Set payload
	 *
	 * @param $payload
	 */
	private function setPayload($payload) {
		$this->payload = $payload;
	}

	/**
	 * Get payload
	 *
	 * @return string
	 */
	public function getPayload() {
		return $this->payload;
	}

	/**
	 * Set start date
	 *
	 * @param DateTime $startDate
	 */
	private function setStartDate($startDate) {
		$this->startDate = $startDate;
	}

	/**
	 * Get start date
	 *
	 * @return DateTime
	 */
	private function getStartDate() {
		return $this->startDate;
	}

	/**
	 * Set end date
	 *
	 * @param DateTime $endDate
	 */
	private function setEndDate($endDate) {
		$this->endDate = $endDate;
	}

	/**
	 * Get end date
	 *
	 * @return DateTime
	 */
	private function getEndDate() {
		return $this->endDate;
	}

	/**
	 * Set list name
	 *
	 * @param $listName
	 */
	private function setListName($listName) {
		$this->listName = $listName;
	}

	/**
	 * Get list name
	 *
	 * @return string
	 */
	private function getListName() {
		return $this->listName;
	}

	/**
	 * Set row limit
	 *
	 * @param $rowLimit
	 */
	private function setRowLimit($rowLimit) {
		$this->rowLimit = $rowLimit;
	}

	/**
	 * Get row limit
	 *
	 * @return int
	 */
	private function getRowLimit() {
		return $this->rowLimit;
	}
}
