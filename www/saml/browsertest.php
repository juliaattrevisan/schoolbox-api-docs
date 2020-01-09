<?php
use Wolfcast\BrowserDetection;
use Symfony\Component\HttpFoundation\IpUtils;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

require "saml-setup.php";

$browser = new BrowserDetection();

echo "\nBrowser: " . $browser->getName() ;
echo "\nPlatform: " . $browser->getPlatform() ;
echo "\nMobile: " . ($browser->isMobile() ? 'Yes' : 'No');

echo "\nAttempt to use Kerberos/WIA with SAML for this browser? " . '' ."\n";
var_dump(useSAMLKerberos());

if (trim(Config::get('saml_krb_whitelist'))=='') {
    echo "\nIs this IP address (" . Session::getIP() . ") within the Kerberos/WIA whitelist?\n";
    var_dump(IpUtils::checkIp(Session::getIP(), explode(',', Config::get('saml_krb_whitelist'))));
}
echo "</pre>";
