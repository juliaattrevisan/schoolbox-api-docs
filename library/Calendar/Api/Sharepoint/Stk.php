<?php
/**
 * Calendar_Api_Sharepoint_Stk
 *
 * St. Kentigern uses iport, a customisation of Sharepoint.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Sharepoint_Stk implements Calendar_Api_Interface {

	/* An instance of the built-in php5 soap
	 */
	private $soap = null;

	/* The place where we found the wsdl file, which was then
	 * saved locally at $localWsdl
	 */
	private $wsdl = 'http://iportexternal/_vti_bin/Lists.asmx?wsdl';
	private $localWsdl = '../library/Calendar/Api/Sharepoint/Stk/Lists.wsdl';

	/* STK credentials
	 * User account to authenticate with. (Must have read/write/edit permissions to given Lists)
	 */
	private $credentials = array(
		'login' => 'alaress',
		'password' => 'Tjs782',
		'authentication' => SOAP_AUTHENTICATION_BASIC,
		'trace' => true,
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		try {

			/* Create authentication instances
			 */

//			/* Security name space definition
//			 */
//			$strWSSENS = "http://schemas.xmlsoap.org/ws/2002/07/secext";
//
//			/* Create Soap Variables for Username and Password
//			 */
//			$soapUser = new SoapVar('NTDOMAIN\alaress', XSD_STRING, null, $strWSSENS, null, $strWSSENS);
//			$soapPass = new SoapVar('Tjs782', XSD_STRING, null, $strWSSENS, null, $strWSSENS);
//
//			/* Object for Auth Class
//			 */
//			$wsseAuth = new Alaress_Soap_WSSEAuth($soapUser, $soapPass);
//
//			/* Create soapVar out of auth class object
//			 */
//			$soapVarWSSEAuth = new SoapVar($wsseAuth, SOAP_ENC_OBJECT, null, $strWSSENS, 'UsernameToken', $strWSSENS);
//
//			/* Create object for token class
//			 */
//			$wsseToken = new Alaress_Soap_WSSEToken($soapVarWSSEAuth);
//
//			/* Create soapVar out of token class object
//			 */
//			$soapVarWSSEToken = new SoapVar($wsseToken, SOAP_ENC_OBJECT, null, $strWSSENS, 'UsernameToken', $strWSSENS);
//
//			/* Create soapVar for Security node
//			 */
//			$soapVarHeaderVal = new SoapVar($soapVarWSSEToken, SOAP_ENC_OBJECT, null, $strWSSENS, 'Security', $strWSSENS);
//
//			/* Create header object out of security soapVar
//			 */
//			$soapVarWSSEHeader = new SoapHeader($strWSSENS, 'Security', $soapVarHeaderVal, true);

			/* Create soap client
			*/
			$soapClient = new SoapClient($this->getWsdl(), $this->getCredentials());
//			$soapClient->__setSoapHeaders(array($soapVarWSSEHeader));
		}
		catch (SoapFault $fault) {
			var_dump($fault);
			die();
		}

		$this->setSoap($soapClient);

//		$this->test();
	}

	/**
	 * Test method
	 */
//	private function test() {
//		$start = new Alaress_Date_Format(date('Y-m-d') . ' 00:00:01', 'y-m-d h:i:s');
//		$end = new Alaress_Date_Format(date('Y-m-d') . ' 23:59:59', 'y-m-d h:i:s');
//
////		$startDate = $start->getUsingFormat(DATE_ISO8601);
////		$endDate = $end->getUsingFormat(DATE_ISO8601);
//
//		$startDate = $start->getDatabase();
//		$endDate = $end->getDatabase();
////		$startDate = '2012-07-19 00:00:01';
////		$endDate = '2012-07-19 23:59:59';
//
//		$xmlPayload = new Calendar_Api_Sharepoint_Stk_Payload($startDate, $endDate);
//
//		$response = $this->getSoap()->GetListItems(array(
//			'listName' => 'Master Calendar',
//			'query' => $xmlPayload->getPayload(),
//			'rowLimit' => 5,
//		));
//
//		$allEvents = $xmlPayload->parseXml($response->GetListItemsResult->any);
////print_r($xmlPayload->getPayload());
////		print_r($allEvents);
//		$stack = new Calendar_Event_Collection();
//
//		foreach($allEvents->rsdata->zrow as $currentEvent) {
//			$attribs = $currentEvent->attributes();
//
//			$event = new Calendar_Event_Type_Day();
//			$event->setAuthorName('');
//			$event->setAuthorId('');
//			$event->setEventId(1);
//			$event->setStartTime(new Alaress_Date_Format((string)$attribs->ows_EventDate, 'y-m-d h:i:s'));
//			$event->setEndTime(new Alaress_Date_Format((string)$attribs->ows_EndDate, 'y-m-d h:i:s'));
//			$event->setType('');
//			$event->setNote((string)$attribs->ows_Title);
//			$event->setAccess(-1);
//			$event->setEventType('');
//			$event->setColour('');
//			$event->setPublishDate('2012-07-01 01:01:01');
//			$event->setSid('');
//			$event->setCategoryId('');
//			$event->setCategoryName('');
//			$event->setAttendance('');
//
//			$stack->add($event);
//		}
//
//		return $stack;
//	}

	/**
	 * Load wsdl file via curl
	 *
	 * @return bool|string
	 */
	private function loadWsdl() {
		$curl = new Alaress_Curl_Url($this->getWsdl());

		$curl->addOptions(array(
			CURLOPT_USERPWD => "alaress:Tjs782",
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
		));

		$result = $curl->exec()->getResult();

		if ($result) {
			return $result;
		}

		/* TODO: Throw exception here
		 */

		return false;
	}

	/**
	 * Get day events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getDayEvents($currentDay) {
		$now = new Alaress_Date_Format($currentDay, 'U');

		$start = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:01', 'y-m-d h:i:s');
		$end = new Alaress_Date_Format($now->getDatabaseDate() . ' 23:59:59', 'y-m-d h:i:s');

		$startDate = $start->getDatabase();
		$endDate = $end->getDatabase();

		$xmlPayload = new Calendar_Api_Sharepoint_Stk_Payload($startDate, $endDate);

		$response = $this->getSoap()->GetListItems(new SoapVar($xmlPayload->getPayload(), XSD_ANYXML));

		$allEvents = $xmlPayload->parseXml($response->GetListItemsResult->any);

		$stack = new Calendar_Event_Collection();

		foreach($allEvents->rsdata->zrow as $currentEvent) {
			$attribs = $currentEvent->attributes();

			$event = new Calendar_Event_Type_Day();
			$event->setAuthorName('');
			$event->setAuthorId('');
			$event->setEventId(1);
			$event->setStartTime(new Alaress_Date_Format((string)$attribs->ows_EventDate, 'y-m-d h:i:s'));
			$event->setEndTime(new Alaress_Date_Format((string)$attribs->ows_EndDate, 'y-m-d h:i:s'));
			$event->setType('');
			$event->setNote((string)$attribs->ows_Title);
			$event->setAccess(-1);
			$event->setEventType('');
			$event->setColour('');
			$event->setPublishDate('2012-07-01 01:01:01');
			$event->setSid('');
			$event->setCategoryId('');
			$event->setCategoryName('');
			$event->setAttendance('');

			$stack->add($event);
		}

		return $stack;

		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}

	/**
	 * Get student birthdays from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getStudentBirthdays($currentDay) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}

	/**
	 * Get submission events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getSubmissionEvents($currentDay) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}

	/* Getters/Setters
	 */

	/**
	 * Set soap
	 *
	 * @param $soap
	 */
	private function setSoap($soap) {
		$this->soap = $soap;
	}

	/**
	 * Get soap
	 *
	 * @return null
	 */
	private function getSoap() {
		return $this->soap;
	}

	/**
	 * Get wsdl
	 *
	 * @return string
	 */
	private function getWsdl() {
		return $this->wsdl;
	}

	/**
	 * Get credentials
	 *
	 * @return array
	 */
	private function getCredentials() {
		return $this->credentials;
	}

	/**
	 * Get local wsdl location
	 *
	 * @return string
	 */
	private function getLocalWsdl() {
		return $this->localWsdl;
	}
}
