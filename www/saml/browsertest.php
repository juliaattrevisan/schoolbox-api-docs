<?php
use BrowscapPHP\Browscap;
use Doctrine\Common\Cache\FilesystemCache;
use Psr\Log\NullLogger;
use Roave\DoctrineSimpleCache\SimpleCacheAdapter;
use Symfony\Component\HttpFoundation\IpUtils;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

require "saml-setup.php";

echo "<pre>";
$bc = new Browscap(
    new SimpleCacheAdapter(
        new Alaress_Saml_CacheReader(__DIR__ . '/../../tools/browscap')
    ),
    new NullLogger()
);

$current_browser = $bc->getBrowser();
echo "\nBrowser: " . $current_browser->browser . "\n";
echo "Platform: " . $current_browser->platform . "\n";
echo "Device Type: " . $current_browser->device_type . "\n";

echo "\nAttempt to use Kerberos/WIA with SAML for this browser? " . '' ."\n";
var_dump(useSAMLKerberos());

if (trim(Config::get('saml_krb_whitelist'))=='') {
    echo "\nIs this IP address (" . Session::getIP() . ") within the Kerberos/WIA whitelist?\n";
    var_dump(IpUtils::checkIp(Session::getIP(), explode(',', Config::get('saml_krb_whitelist'))));
}
echo "</pre>";
