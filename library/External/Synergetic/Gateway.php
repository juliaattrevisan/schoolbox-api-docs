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
     * @throws SoapFault
     *
     * @return string Web service output (XMLOutput, ErrorMessage, SourceIPAddress)
     */
    public function changeCommunityLoginPassword($communityId, $currentPassword, $newPassword) {
        try {
            $output = $this->client->ChangeCommunityLoginPassword(
				$this->getXmlInput(
					array(
						'ID'              => $communityId,
						'CurrentPassword' => $currentPassword,
						'NewPassword'     => $newPassword,
					)
				)
			);
			return $output;
        }
		catch (Exception $e) {
			echo $e->getMessage();
        }
    }


	/**
	 * Creates input for the Synergetic API.
	 *
	 * The Synergetic API stupidly accepts XML input as a text attribute that
	 * happens to be encoded XML. This method makes generating that XML a bit
	 * less painful.
	 *
	 * @param array $params        The parameters to pass in XMLInput.
	 * @param bool  $add_vendor_id If false, then don't add the VendorTokenGUID
	 *                             and VendorPassword to the input.
	 *
	 * @return array A wrapper for an XML string representation of $params.
	 *               This can be passed as a parameter to a web service call.
	 */
	protected function getXmlInput($params, $add_vendor_id = true) {
		$input_doc = new DOMDocument();
		$input_doc->normalizeDocument();
		$input_element = $input_doc->createElement('InputXML');
		if ($add_vendor_id) {
			$input_element->appendChild($input_doc->createElement('VendorTokenGUID', $this->getVendorTokenGUID()));
			$input_element->appendChild($input_doc->createElement('VendorPassword',  $this->getVendorPassword()));
		}
		foreach ($params as $key => $value) {
			$input_element->appendChild($input_doc->createElement($key, $value));
		}
		return array(
			'aInputParamObj' => array(
				'XMLInput' => trim($input_doc->saveXML($input_element)),
			)
		);
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