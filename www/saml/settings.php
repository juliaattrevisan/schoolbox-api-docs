<?php
/**
 * SAMPLE Code to demonstrate how provide SAML settings.
 *
 * The settings are contained within a OneLogin_Saml_Settings object. You need to
 * provide, at a minimum, the following things:
 *
 *  - idpSingleSignOnUrl
 *    This is the URL to forward to for auth requests.
 *    It will be provided by your IdP.
 *
 *  - idpPublicCertificate
 *    This is a certificate required to authenticate your request.
 *    This certificate should be provided by your IdP.
 * 
 *  - spReturnUrl
 *    The URL that the IdP should redirect to once the authorization is complete.
 *    You must provide this, and it should point to the consume.php script or its equivalent.
 */

require_once __DIR__.'/../../include/Bootstrap.php';

$settings = new OneLogin_Saml_Settings();

// When using Service Provider Initiated SSO (starting at index.php), this URL asks the IdP to authenticate the user.
$settings->idpSingleSignOnUrl = 'https://WIN-NFQC027AIDJ.schoolbox.alrs/adfs/ls';
$settings->idpSingleSignOnUrl = Config::get('saml_sso_url');

// The certificate for the users account in the IdP
$settings->idpPublicCertificate = preg_replace("/\r/m", "", Config::get('saml_idp_certificate'));

// The URL where to the SAML Response/SAML Assertion will be posted
$settings->spReturnUrl = 'http://'.$_SERVER['HTTP_HOST'].'/saml/consume.php';

// Name of this application
$settings->spIssuer = 'schoolbox';

// Tells the IdP to return the email address of the current user
#$settings->requestedNameIdFormat = OneLogin_Saml_Settings::NAMEID_EMAIL_ADDRESS;
$settings->requestedNameIdFormat = OneLogin_Saml_Settings::NAMEID_TRANSIENT;

return $settings;
