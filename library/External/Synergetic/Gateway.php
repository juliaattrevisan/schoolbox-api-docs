<?php
/**
 * Synergetic SOAP API Gateway
 *
 * @author      Ryan Hughes <ryan.hughes@alaress.com.au>
 * @copyright   Copyright 2011 Alaress. All Rights Reserved.
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Synergetic_Gateway {

    protected $client,
              $vendorTokenGUID,
              $vendorPassword;

    /**
	 * Constructor
	 *
     * @param string $wsdlUrl SOAP web services WSDL URL (external_web_services_url)
     * @param string $vendorTokenGUID Synergetic VendorTokenGUID (external_vendor_token_guid)
     * @param string $vendorPassword Synergetic VendorPassword (external_vendor_password)
     */
    public function __construct($wsdlUrl, $vendorTokenGUID, $vendorPassword) {
        $this->setVendorTokenGUID($vendorTokenGUID);
        $this->setVendorPassword($vendorPassword);

        $this->client = new SoapClient($wsdlUrl, array('trace' => true));
    }

    /**
     * Updates a Synergetic user password
     *
	 * @param int    $communityId
     * @param string $currentPassword
     * @param string $newPassword
	 * @param string $username Schoolbox/Synergetic community username
     * @throws SoapFault
     *
     * @return string Web service output (XMLOutput, ErrorMessage, SourceIPAddress)
     */
    public function changeCommunityLoginPassword($communityId, $currentPassword, $newPassword, $username) {
		//
		// LoginUser
		$login_input = new DOMDocument();
//		$login_input->loadXML('
//			<InputXML>
//				<VendorTokenGUID></VendorTokenGUID>
//				<VendorPassword></VendorPassword>
//				<USERNAME></USERNAME>
//				<PASSWORD></PASSWORD>
//				<INSTALLGUID></INSTALLGUID>
//			</InputXML>
//		', LIBXML_NOXMLDECL);
//		$login_input->xmlStandalone = true;
		$login_input->normalizeDocument();
		$guid          = bin2hex(openssl_random_pseudo_bytes(16));
		$input_element = $login_input->createElement('InputXML');
		$input_element->appendChild($login_input->createElement('VendorTokenGUID', $this->getVendorTokenGUID()));
		$input_element->appendChild($login_input->createElement('VendorPassword', $this->getVendorPassword()));
		$input_element->appendChild($login_input->createElement('USERNAME', $communityId));
		$input_element->appendChild($login_input->createElement('PASSWORD', $currentPassword));
		$input_element->appendChild($login_input->createElement('INSTALLGUID', $guid));
		$login_input->appendChild($input_element);
		$login_input->formatOutput = true;
//		var_dump($login_input);
//		var_dump($login_input->InputXML);
//		var_dump($login_input->InputXML->VendorTokenGUID);
//		$login_input->InputXML->VendorTokenGUID->appendChild($login_input->createTextNode($this->getVendorTokenGUID()));
//		$login_input->InputXML->VendorPassword->appendChild($login_input->createTextNode($this->getVendorPassword()));
//		$login_input->InputXML->USERNAME->appendChild($login_input->createTextNode($username));
//		$login_input->InputXML->PASSWORD->appendChild($login_input->createTextNode($currentPassword));
//		//
//		// Shit out a GUID
//		$login_input->InputXML->INSTALLGUID     = bin2hex(openssl_random_pseudo_bytes(16));
//		var_dump($login_input->InputXML);
//		var_dump($login_input->InputXML->VendorTokenGUID);
		var_dump($login_input->saveXML($input_element));
		$output = $this->client->GeneratePIN(
			array(
				'aInputParamObj' => array(
					'XMLInput' => trim($login_input->saveXML($input_element))
				),
			)
		);
//		var_dump($this->client->__getFunctions());
		echo nl2br(htmlspecialchars($this->client->__getLastRequestHeaders()))."<br />";
		echo nl2br(htmlspecialchars($this->client->__getLastRequest()))."<br />";
		echo nl2br(htmlspecialchars($this->client->__getLastResponseHeaders()))."<br />";
		echo nl2br(htmlspecialchars($this->client->__getLastResponse()))."<br />";
		var_dump($output);
		exit;


		// change password
		// LogoutUser
        $input =
                "<aInputParamObj>".
                "<InputXML>" .
                        "<VendorTokenGUID>%s</VendorTokenGUID>" .
                        "<VendorPassword>%s</VendorPassword>" .
                        "<VersionNumber>1.2</VersionNumber>" .
                        "<ConfigFileName></ConfigFileName>" .
                        "<ID>%d</ID>" .
                        "<CurrentPassword>%s</CurrentPassword>" .
                        "<NewPassword>%s</NewPassword>" .
                 "</InputXML>".
                "</aInputParamObj>";


        $input = sprintf(
			$input,
            $this->getVendorTokenGUID(),
            $this->getVendorPassword(),
            $communityId,
            $currentPassword,
            $newPassword
		);
		var_dump($input);

        try {
			//
			// XMLInput is passed as a string, instead of as, you know, XML.
			// Words cannot express how stupid that is!
            $output = $this->client->ChangeCommunityLoginPassword(array(
				'aInputParamObj' => array(
					'XMLInput' => $input
				)
			));
//			$b = new DOMDocument();
//			$b->loadXML($output->GetCommunityRelationshipsResult->XMLOutput);
			echo htmlspecialchars($output->GetCommunityDetailsResult->XMLOutput);
			var_dump($output);
			exit;
			return $output;
        }
		catch (Exception $e) {
			echo $e->getMessage();
        }
    }

	public function loginUser($username, $password, $installGUID) {
		$input = "
			<InputXML>
				<VendorTokenGUID>%s</VendorTokenGUID>
				<VendorPassword>%s</VendorPassword>
				<USERNAME>%s</USERNAME>
				<PASSWORD>%s</PASSWORD>
				<INSTALLGUID>%s</INSTALLGUID>
			</InputXML>
		";

		$input = sprintf(
			$input,
			$this->getVendorTokenGUID(),
			$this->getVendorPassword(),
			$username,
			$password,
			$installGUID
		);
	}


	public function insertWkAttendances($fileType, $fileYear, $fileSemester, $campus, $classCode, $attendanceDate, $staffID, $timetableGroup) {
		$input = "
			<InputXML>
				<VendorTokenGUID>%s</VendorTokenGUID>
				<VendorPassword>%s</VendorPassword>
				<FileType>%s</FileType>
				<FileYear>%s</FileType>
				<FileSemester>%s</FileSemester>
				<ClassCampus>%s</ClassCampus>
				<ClassCode>%s</ClassCode>
				<AttendanceDate>%s</AttendanceDate>
				<StaffID>%s</StaffID>
				<TimeTableGroup>%s</TimeTableGroup>
			</InputXML>
		";

		$input = sprintf(
			$input,
			$this->getVendorTokenGUID(),
			$this->getVendorPassword(),
			$fileType,
			$fileYear,
			$fileSemester,
			$campus,
			$classCode,
			$attendanceDate,
			$staffID,
			$timetableGroup
		);

		print_r($input);
	}

	public function selectWkAttendances($wkAttendanceMasterSeq) {
		$input = "
			<InputXML>
				<VendorTokenGUID>%s</VendorTokenGUID>
				<VendorPassword>%s</VendorPassword>
				<WKATTENDANCEMASTERSEQ>%s</WKATTENDANCEMASTERSEQ>
			</InputXML>
		";

		$input = sprintf(
			$input,
			$this->getVendorTokenGUID(),
			$this->getVendorPassword(),
			$wkAttendanceMasterSeq
		);
	}

	public function updateWkAttendances($wkAttendanceMasterSeq, $absentIDs) {
		$csv = implode(',', $absentIDs);

		$input = "
			<InputXML>
				<VendorTokenGUID>%s</VendorTokenGUID>
				<VendorPassword>%s</VendorPassword>
				<WKATTENDANCEMASTERSEQ>%s</WKATTENDANCEMASTERSEQ>
				<IDCSV>%s</IDCSV>
			</InputXML>
		";

		$input = sprintf(
			$input,
			$this->getVendorTokenGUID(),
			$this->getVendorPassword(),
			$wkAttendanceMasterSeq,
			$csv
		);
	}

	public function transferWkAttendances($wkAttendanceMasterSeq) {
		$input = "
			<InputXML>
				<VendorTokenGUID>%s</VendorTokenGUID>
				<VendorPassword>%s</VendorPassword>
				<WKATTENDANCEMASTERSEQ>%s</WKATTENDANCEMASTERSEQ>
			</InputXML>
		";

		$input = sprintf(
			$input,
			$this->getVendorTokenGUID(),
			$this->getVendorPassword(),
			$wkAttendanceMasterSeq
		);
	}


    public function logInCommunityMemberToSynCommPortal($parameters) {
        return $this->client->LogInCommunityMemberToSynCommPortal($parameters);
    }

    public function logOutCommunityMemberFromSynCommPortal($parameters) {
        return $this->client->LogOutCommunityMemberFromSynCommPortal($parameters);
    }

    public function getCommunityRelationships($parameters) {
        return $this->client->GetCommunityRelationships($parameters);
    }

    public function getCommunityDetails($parameters) {
        return $this->client->GetCommunityDetails($parameters);
    }

    public function getCommunityGroups($parameters) {
        return $this->client->GetCommunityGroups($parameters);
    }

    public function getGeneralLedgerAccounts($parameters) {
        return $this->client->GetGeneralLedgerAccounts($parameters);
    }

    public function getCreditors($parameters) {
        return $this->client->GetCreditors($parameters);
    }

    public function getPurchaseOrders($parameters) {
        return $this->client->GetPurchaseOrders($parameters);
    }

    public function createPurchaseOrder($parameters) {
        return $this->client->CreatePurchaseOrder($parameters);
    }

    public function cancelPurchaseOrder($parameters) {
        return $this->client->CancelPurchaseOrder($parameters);
    }

    public function __destruct() {
        unset ($this->client);
    }



	/* Getters/Setters
	 */


	/**
	 * Set vendor password
	 *
	 * @param $vendorPassword
	 */
	private function setVendorPassword($vendorPassword) {
		$this->vendorPassword = $vendorPassword;
	}

	/**
	 * Get vendor password
	 *
	 * @return string
	 */
	private function getVendorPassword() {
		return $this->vendorPassword;
	}

	/**
	 * Set vendor token guid
	 *
	 * @param $vendorTokenGUID
	 */
	private function setVendorTokenGUID($vendorTokenGUID) {
		$this->vendorTokenGUID = $vendorTokenGUID;
	}

	/**
	 * Get vendor token guid
	 *
	 * @return string
	 */
	private function getVendorTokenGUID() {
		return $this->vendorTokenGUID;
	}

}