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

        $this->client = new SoapClient($wsdlUrl);
    }

    /**
     * Updates a Synergetic user password
     *
     * @param integer $communityId Synergetic community id
     * @param string $currentPassword
     * @param string $newPassword
     * @throws SoapFault
     *
     * @return string Web service output (XMLOutput, ErrorMessage, SourceIPAddress)
     */
    public function changeCommunityLoginPassword($communityId, $currentPassword, $newPassword) {
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

        try {
            return $this->client->ChangeCommunityLoginPassword(array(
				'aInputParamObj' => array(
					'XMLInput' => $input
				)
			));
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