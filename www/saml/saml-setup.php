<?php

use Alaress\Http\Cache;
use Schoolbox\Admin\Home\SamlCertHelper;
use Wolfcast\BrowserDetection;
use Symfony\Component\HttpFoundation\IpUtils;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\IdPMetadataParser;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$logger = new \Alaress\Lumberjack\Logger(STDOUT, STDOUT, Config::get('saml_debug') ? \Monolog\Logger::DEBUG : \Monolog\Logger::INFO, [], true, 'saml');

$spBaseUrl = 'https://' . Config::get('site_hostname') . '.' . Config::get('site_domain');


$settingsInfo = array (
    'debug' => (bool)Config::get('saml_debug'),
    'idp' => [
        'entityId' =>  Config::get('saml_entityid') ?: Config::get('saml_idp_metadata_url'),
        'singleSignOnService' => array (
            'url' => Config::get('saml_sso_url'),
        ),
        // SLO endpoint info of the IdP.
        'singleLogoutService' => array (
            'url' => Config::get('saml_slo_url'),
        ),
        'x509cert' => preg_replace("/\r/m", "", Config::get('saml_idp_certificate')),
    ],
    'security' => [
        'requestedAuthnContext'             => false,
        'signatureAlgorithm'                => XMLSecurityKey::RSA_SHA256,
        'digestAlgorithm'                   => XMLSecurityDSig::SHA256,
        'lowercaseUrlencoding'              => true,
        'wantNameId'                        => true,
        'logoutRequestSigned'               => Config::get('saml_private_cert') ? true : false,
        'logoutResponseSigned'              => Config::get('saml_private_cert') ? true : false,

    ],
);

// requestedAuthnContext
if (Config::get('saml_auth_context') !== 'unspecified') {
    $settingsInfo['security']['requestedAuthnContext'] = [useSAMLKerberos($logger) ? Constants::AC_WINDOWS : Constants::AC_PASSWORD_PROTECTED];
    $settingsInfo['security']['requestedAuthnContextComparison'] = Config::get('saml_auth_comparison');
}

$logger->debug(json_encode($settingsInfo));

try {
    $url = new \Schoolbox\Url\Uri(Config::get('saml_idp_metadata_url'));
    $idpMetadataUrl = (string)$url;

    /** @var Cache $cache */
    $cache       = $kernel->get('httpClient.cache');
    $idpMetadata = $cache->request(
        $idpMetadataUrl,
        SamlCertHelper::SAML_CACHE_PREFIX,
        [],
        SamlCertHelper::SAML_CACHE_TIMEOUT,
    );

    // if cache exists, update $setting info from cache
    if (!empty($idpMetadata)) {
        try {
            $settingsInfo = IdPMetadataParser::injectIntoSettings($settingsInfo, IdPMetadataParser::parseXML($idpMetadata));
        } catch (Exception $e) {
            $cache->invalidate($idpMetadataUrl);
            $logger->warning("Invalidating cache as we failed to get valid metadata from IDP");
        }
    }
} catch (Exception $e) {
    //noop, metadata url invalid
}

/**
 * Exports the private key for use as it is stored passphrase encoded
 */
$privateKey = false;

$privateKeyResource = openssl_pkey_get_private(Config::get('saml_private_cert'), sha1(strtolower(trim(Config::get('site_school_code')))));
if ($privateKeyResource !== false) {
    openssl_pkey_export($privateKeyResource, $privateKey);
}

$settingsInfo['sp'] = [
        'entityId' => Config::get('saml_sp_entityid') ?: $spBaseUrl,
        'assertionConsumerService' => [
            'url' => $spBaseUrl . '/saml/consume.php',
            'binding' => Constants::BINDING_HTTP_POST,

        ],
        'singleLogoutService' => [
            'url' => $spBaseUrl . '/saml/consume.php?sls',
            'binding' => Constants::BINDING_HTTP_REDIRECT,
        ],
        'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,
        'x509cert' => Config::get('saml_public_cert'),
        'privateKey' => $privateKey ? $privateKey : Config::get('saml_public_cert'),
    ];

/**
 * Checks if the user is in the correct IP range (by the whitelist)
 * and also of a supported browser / platform that supports Kerberos Auth
 *
 * @return boolean
 */
function useSAMLKerberos(\Alaress\Lumberjack\Logger $logger)
{

    // If whitelist not in use, don't use kerberos at all
    if (trim(Config::get('saml_krb_whitelist')) == '') {
        $logger->debug("Kerberos whitelist not in use");
        return false;
    }

    // Check if the user is in the IP whitelist


    if (!IpUtils::checkIp(Session::getIP(), array_map('trim', explode(',', Config::get('saml_krb_whitelist'))))) {
        $logger->debug("IP not in kerberos whitelist");
        return false;
    }

    /**
     * Check if the users platform/browser supports kerberos
     *
     * Acceptance matrix:
     * via: (http://en.wikipedia.org/wiki/Integrated_Windows_Authentication#Supported_web_browsers)
     *  - Must be Desktop/Laptop
     *  - IE on Windows
     *  - Chrome on Windows and MacOSX
     *  - Safari on MacOSX
     *  - Firefox on Windows (if enabled in the Firefox install)
     *
     * Errs on the side of kerberos than non-kerberos
     */
    $logger->debug("IP in kerberos whitelist, attempting browser/platform check");

    $browser = new BrowserDetection();

    $logger->debug(json_encode($browser->getUserAgent()));
    if (!$browser->isMobile()) {
        if ($browser->getPlatform() == BrowserDetection::PLATFORM_WINDOWS || $browser->getPlatform() == BrowserDetection::PLATFORM_MACINTOSH) {
            switch ($browser->getName()) {
                case BrowserDetection::BROWSER_IE:
                    return true;

                case BrowserDetection::BROWSER_EDGE:
                    return true;

                case BrowserDetection::BROWSER_CHROME:
                    if (Config::get('saml_krb_chrome')) {
                        return true;
                    } else {
                        return false;
                    }

                case BrowserDetection::BROWSER_SAFARI:
                    if (Config::get('saml_krb_safari')) {
                        return true;
                    } else {
                        return false;
                    }

                case BrowserDetection::BROWSER_FIREFOX:
                    if (Config::get('saml_krb_firefox')) {
                        return true;
                    } else {
                        return false;
                    }

                default:
                    return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}
