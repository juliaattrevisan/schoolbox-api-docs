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
     * @param string $wsdlUrl SOAP web services WSDL URL (external_web_services_url)
     * @param string $vendorTokenGUID Synergetic VendorTokenGUID (external_vendor_token_guid)
     * @param string $vendorPassword Synergetic VendorPassword (external_vendor_password)
     */
    public function __construct($wsdlUrl, $vendorTokenGUID, $vendorPassword) {

        $this->vendorTokenGUID = $vendorTokenGUID;
        $this->vendorPassword = $vendorPassword;

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
                "<XMLInput>" .
                        "<VendorTokenGUID>%s</VendorTokenGUID>" .
                        "<VendorPassword>%s</VendorPassword>" .
                        "<VersionNumber>1.2</VersionNumber>" .
                        "<ConfigFileName></ConfigFileName>" .
                        "<ID>%d</ID>" .
                        "<CurrentPassword>%s</CurrentPassword>" .
                        "<NewPassword>%s</NewPassword>" .
                 "</XMLInput>".
                "</aInputParamObj>";


        $input = sprintf($input,
            $this->vendorTokenGUID,
            $this->vendorPassword,
            $communityId,
            $currentPassword,
            $newPassword);


        try{
            return $this->client->ChangeCommunityLoginPassword(array('aInputParamObj'=>array('XMLInput'=>$input)));
        } catch (Exception $e) {
                echo $e->getMessage();
        }

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

}