<?php
use BrowscapPHP\Browscap;
require_once __DIR__.'/../../library/Bootstrap.php';

require "saml-setup.php";

echo "<pre>";
$bc = new Browscap();
$cacheDir = __DIR__.'/../../tools/browscap';
$adapter = new \WurflCache\Adapter\File([\WurflCache\Adapter\File::DIR => $cacheDir, 'readonly'=>true]);
$bc->setCache($adapter);

// Don't attempt to update
$bc->doAutoUpdate = false;
// Use local version of the browscap.ini file
$bc->updateMethod = 'local';
// really ensure we don't attemp to update the browscap.ini file (1 year)
$bc->updateInterval = '31536000';

$current_browser = $bc->getBrowser();
echo "\nBrowser: " . $current_browser->browser . "\n";
echo "Platform: " . $current_browser->platform . "\n";
echo "Device Type: " . $current_browser->device_type . "\n";

echo "\nAttempt to use Kerberos/WIA with SAML for this browser? " . '' ."\n";
var_dump(useSAMLKerberos());

if (trim(Config::get('saml_krb_whitelist'))=='') {
    echo "\nIs this IP address (" . Session::getIP() . ") within the Kerberos/WIA whitelist?\n";
    var_dump(ipInCIDRList(Session::getIP(), Config::get('saml_krb_whitelist')));
}
echo "</pre>";
