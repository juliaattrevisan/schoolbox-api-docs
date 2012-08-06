<?php
/**
 * Calendar_Api_Sharepoint_Stk_Payload
 *
 * See http://www.threewill.com/2011/08/remote-sharepoint-calendars/ for more info
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Sharepoint_Stk_Payload {

	/* Should be in database date format yyyy-mm-dd hh:ii:ss
	 */
	private $startDate = '';
	private $endDate = '';

	/* Special dromedary
	 */
	private $camlQuery = '
		<GetListItems xmlns="http://schemas.microsoft.com/sharepoint/soap/">
			<listName>Master Calendar</listName>
			<rowLimit>5</rowLimit>
			<query>
				<Query>
					<Where>
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
					</Where>
				</Query>
			</query>
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
}
