<?php
/**
 * Calendar_Api_Event_Sharepoint_Stk_PayloadTest
 *
 * See http://www.threewill.com/2011/08/remote-sharepoint-calendars/ for more info
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Event_Sharepoint_Stk_PayloadTest {

	/* Name or guid of the list
	 */
	private $listName = 'Master Calendar';
	private $rowLimit = 100;

	/* Should be in database date format yyyy-mm-dd hh:ii:ss
	 */
	private $startDate = '';
	private $endDate = '';

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
				<Query xmlns="">
					<Where>
						<And>
							<Or>
								<And>
									<Geq>
										<FieldRef Name="EventDate"/>
										<Value Type="DateTime">%s</Value>
									</Geq>
									<Leq>
										<FieldRef Name="EndDate"/>
										<Value Type="DateTime">%s</Value>
									</Leq>
								</And>
								<Or>
									<And>
										<Eq>
											<FieldRef Name="EventDate"/>
											<Value Type="DateTime">%s</Value>
										</Eq>
										<Eq>
											<FieldRef Name="fAllDayEvent"/>
											<Value Type="Number">1</Value>
										</Eq>
									</And>
									<And>
										<Leq>
											<FieldRef Name="EventDate"/>
											<Value Type="DateTime">%s</Value>
										</Leq>
										<Geq>
											<FieldRef Name="EndDate"/>
											<Value Type="DateTime">%s</Value>
										</Geq>
									</And>
								</Or>
							</Or>
							<Eq>
								<FieldRef Name="_ModerationStatus"/>
								<Value Type="Number">0</Value>
							</Eq>
						</And>
					</Where>
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
					<FieldRef Name="RecurrenceID"/>
					<FieldRef Name="RecurrenceData"/>
				</ViewFields>
			</viewFields>
			<queryOptions>
				<QueryOptions xmlns="">
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
	 * @param $startDate
	 * @param $endDate
	 */
	public function __construct($startDate, $endDate) {
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
		$payload = sprintf(
			$this->getCamlQuery(),
			$this->getListName(),
			$this->getRowLimit(),
			$this->getStartDate(),
			$this->getEndDate(),
			$this->getStartDate(),
			$this->getStartDate(),
			$this->getEndDate()
		);

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
	 * @param $startDate
	 */
	private function setStartDate($startDate) {
		$this->startDate = $startDate;
	}

	/**
	 * Get start date
	 *
	 * @return string
	 */
	private function getStartDate() {
		return $this->startDate;
	}

	/**
	 * Set end date
	 *
	 * @param $endDate
	 */
	private function setEndDate($endDate) {
		$this->endDate = $endDate;
	}

	/**
	 * Get end date
	 *
	 * @return string
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
