<?php

namespace Schoolbox\Admin\Home;

use Alaress\Exception\UnreachableException;
use Alaress_Date_Format;
use Alaress_Saml_Cache;
use Laminas\Uri\Exception\InvalidArgumentException;
use Laminas\Uri\Exception\InvalidUriException;
use Laminas\Uri\Uri;
use OneLogin\Saml2\IdPMetadataParser;
use OneLogin\Saml2\Utils;
use Schoolbox\Application\Exception as SchoolboxException;


/**
 * Functions for processing SAML certificate
 */
class SamlCertHelper
{
    /**
     * Flag to force this class to pretend that any supplied metadata url is offline/unavailable.
     * Used for unit testing only.
     */
    public const FORCE_METADATA_URL_OFFLINE = 'offline';

    private const IDP_CERT_EMPTY      = 0;     // config field empty
    private const IDP_CERT_UNPARSABLE = 1;     // unable to parse cert from value
    private const IDP_CERT_OK         = 2;     // able to parse cert from value

    private const METADATA_URL_EMPTY      = 0; // config empty
    private const METADATA_URL_OFFLINE    = 1; // URL invalid/offline/unreachable
    private const METADATA_URL_UNPARSABLE = 2; // Fetched content, but could not parse cert from it.
    private const METADATA_URL_OK         = 3; // Fetched content, parsed a cert

    public const LIGHT_ALERT   = 'alert';
    public const LIGHT_WARNING = 'warning';
    public const LIGHT_SUCCESS = 'success';

    private static Alaress_Saml_Cache $samlCache;


    /**
     * @param string                   $idpCertificate
     * @param string                   $idpMetadataUrl
     * @param string|null              $metadataOverride If 'offline', pretend URL is offline/unreachable.
     *                                                   If non-null, pretend URL is online and returns this content.
     * @param Alaress_Date_Format|null $nowOverride      Enable 'now' to be overridden for deterministic unit testing.
     * @return SamlWidget
     */
    public static function buildWidget(
        string $idpCertificate,
        string $idpMetadataUrl,
        Alaress_Saml_Cache $samlCache,
//        string $metadataOverride = null,
        Alaress_Date_Format $nowOverride = null,

    ): SamlWidget {
        self::$samlCache = $samlCache;
        $now = $nowOverride ?? Alaress_Date_Format::now();

        // Derive idp certificate status + daysToExpire
        if (trim($idpCertificate) === "") {
            $idpCertDaysToExpiry = null;
            $idpCertStatus       = self::IDP_CERT_EMPTY;
        } else {
            $idpCertDaysToExpiry = self::getDaysBeforeExpiry(Utils::formatCert($idpCertificate), $now->getUnixTimeStamp());
            $idpCertStatus       = $idpCertDaysToExpiry === null ? self::IDP_CERT_UNPARSABLE : self::IDP_CERT_OK;
        }

        // Derive metadata URL status + daysToExpire
        if ($metadataOverride === null && trim($idpMetadataUrl) === "") {
            // Empty metadata URL
            $metadataUrlStatus    = self::METADATA_URL_EMPTY;
            $metadataDaysToExpire = null;
        } elseif ($metadataOverride === self::FORCE_METADATA_URL_OFFLINE) {
            // Force metadata URL to be offline/unreachable
            $metadataUrlStatus    = self::METADATA_URL_OFFLINE;
            $metadataDaysToExpire = null;
        } else {
            // Derive XML content (respect overridden value if provided)
            if ($metadataOverride !== null) {
                $metadataXml = $metadataOverride;
            } else {
                try {
                    $metadataXml = self::fetchMetadataXml($idpMetadataUrl);
                } catch (InvalidUriException|SchoolboxException\Exception $e) {
                    // Invalid URL | Unable to connect.
                    $metadataXml = null;
                }
            }

            if ($metadataXml === null) {
                $metadataDaysToExpire = null;
                $metadataUrlStatus    = self::METADATA_URL_OFFLINE;
            } else {
                $metadataDaysToExpire = self::parseDaysToExpireFromMetadataXml($metadataXml, $now);
                $metadataUrlStatus    = $metadataDaysToExpire === null
                    ? self::METADATA_URL_UNPARSABLE
                    : self::METADATA_URL_OK;
            }
        }

        return self::buildStatusFromDerived($idpCertStatus, $idpCertDaysToExpiry, $metadataUrlStatus, $metadataDaysToExpire);
    }


    /**
     * Returns XML metadata content from the specified URL.
     * May return NULL if caching fails for some reason.
     *
     * @param string $idpMetadataUrl
     * @return string|null
     * @throws InvalidUriException             If $idpMetadataUrl cannot be recognised as a URL
     * @throws SchoolboxException\Exception    If we cannot connect to ULR
     */
    private static function fetchMetadataXml(string $idpMetadataUrl): ?string
    {
        try {
            $url = new Uri($idpMetadataUrl);
        } catch (InvalidArgumentException $e) {
            // Should never get here. Only thrown in $idpMetadataUrl is not a string, which is
            // impossible due to method's param type declaration.
            throw new UnreachableException($e);
        }

        $validUrl = $url->toString();

        try {
            $idpMetadataCache = self::$samlCache->init($validUrl);
        } catch (\InvalidArgumentException $e) {
            // Should never get here. Only thrown in $idpMetadataUrl is not a string, which is
            // impossible due to method's param type declaration.
            throw new UnreachableException($e);
        }

        return $idpMetadataCache->getStreamContents();
    }


    /**
     * Returns number of days to expiry.
     * Returns NULL if unable to parse/derive a value.
     *
     * @param string              $metadataXml
     * @param Alaress_Date_Format $now
     * @return int|null
     */
    private static function parseDaysToExpireFromMetadataXml(string $metadataXml, Alaress_Date_Format $now): ?int
    {
        try {
            $metadataInfo = IdPMetadataParser::parseXML($metadataXml);
        } catch (\Exception $e) {
            // Unable to parse XML
            return null;
        }

        $certExpiryArray = [];
        foreach (self::getArrayOfSamlCerts($metadataInfo) as $cert) {
            $formattedCert    = Utils::formatCert($cert);
            $daysBeforeExpiry = self::getDaysBeforeExpiry($formattedCert, $now->getUnixTimeStamp());
            if ($daysBeforeExpiry !== null) {
                $certExpiryArray[] = $daysBeforeExpiry;
            }
        }
        // if there are no valid certs
        if (count($certExpiryArray) === 0) {
            return null;
        }
        return max($certExpiryArray);
    }


    /**
     * Build and return a Status object depending on the status and expiry of each contributing element.
     *
     * @param int      $idpCertStatus
     * @param int|null $idpCertDaysToExpiry
     * @param int      $metadataUrlStatus
     * @param int|null $metadataDaysToExpire
     * @return SamlWidget
     */
    private static function buildStatusFromDerived(int $idpCertStatus, ?int $idpCertDaysToExpiry, int $metadataUrlStatus, ?int $metadataDaysToExpire): SamlWidget
    {
        if ($idpCertStatus === self::IDP_CERT_EMPTY && $metadataUrlStatus === self::METADATA_URL_EMPTY) {
            return new SamlWidget(self::LIGHT_ALERT, 'Empty SAML config');
        }

        if ($idpCertStatus === self::IDP_CERT_EMPTY && $metadataUrlStatus === self::METADATA_URL_OFFLINE) {
            return new SamlWidget(self::LIGHT_ALERT, 'Metadata URL offline');
        }

        if ($idpCertStatus === self::IDP_CERT_EMPTY && $metadataUrlStatus === self::METADATA_URL_UNPARSABLE) {
            return new SamlWidget(self::LIGHT_ALERT, 'Metadata invalid');
        }

        if ($idpCertStatus === self::IDP_CERT_EMPTY && $metadataUrlStatus === self::METADATA_URL_OK) {
            return new SamlWidget(self::getLight($metadataDaysToExpire), self::getMessage($metadataDaysToExpire));
        }

        if ($idpCertStatus === self::IDP_CERT_UNPARSABLE && $metadataUrlStatus === self::METADATA_URL_EMPTY) {
            return new SamlWidget(self::LIGHT_ALERT, 'Certificate invalid');
        }

        if ($idpCertStatus === self::IDP_CERT_UNPARSABLE && $metadataUrlStatus === self::METADATA_URL_OFFLINE) {
            return new SamlWidget(self::LIGHT_ALERT, 'Certificate invalid. Metadata URL offline');
        }

        if ($idpCertStatus === self::IDP_CERT_UNPARSABLE && $metadataUrlStatus === self::METADATA_URL_UNPARSABLE) {
            return new SamlWidget(self::LIGHT_ALERT, 'Certificate invalid. Metadata invalid');
        }

        if ($idpCertStatus === self::IDP_CERT_UNPARSABLE && $metadataUrlStatus === self::METADATA_URL_OK) {
            return new SamlWidget(self::getLight($metadataDaysToExpire), self::getMessage($metadataDaysToExpire));
        }

        if ($idpCertStatus === self::IDP_CERT_OK && $metadataUrlStatus === self::METADATA_URL_EMPTY) {
            return new SamlWidget(self::getLight($idpCertDaysToExpiry), self::getMessage($idpCertDaysToExpiry));
        }

        if ($idpCertStatus === self::IDP_CERT_OK && $metadataUrlStatus === self::METADATA_URL_OFFLINE) {
            return new SamlWidget(self::getLight($idpCertDaysToExpiry), self::getMessage($idpCertDaysToExpiry));
        }

        if ($idpCertStatus === self::IDP_CERT_OK && $metadataUrlStatus === self::METADATA_URL_UNPARSABLE) {
            return new SamlWidget(self::getLight($idpCertDaysToExpiry), self::getMessage($idpCertDaysToExpiry));
        }

        // $idpCertStatus === self::IDP_CERT_OK && $metadataUrlStatus === self::METADATA_URL_OK
        return new SamlWidget(self::getLight($metadataDaysToExpire), self::getMessage($metadataDaysToExpire));
    }


    /**
     * Returns what HTML class to apply depending on how many days until the cert expires.
     *
     * @param int $daysToExpire
     * @return string
     */
    private static function getLight(int $daysToExpire): string
    {
        if ($daysToExpire < 7) {
            return self::LIGHT_ALERT;
        }
        if ($daysToExpire < 30) {
            return self::LIGHT_WARNING;
        }
        return self::LIGHT_SUCCESS;
    }


    /**
     * Returns a message to display depending on how many days until the cert expires
     *
     * @param int $daysToExpire
     * @return string
     */
    private static function getMessage(int $daysToExpire): string
    {
        $absDaysBeforeExpiry = abs($daysToExpire);
        // get plural/singular form of 'day'
        $dayOrDays = $absDaysBeforeExpiry === 1 ? "day" : "days";

        if ($daysToExpire < 0) {
            return 'Expired';
        }
        if ($daysToExpire < 30) {
            return "Expires in $absDaysBeforeExpiry $dayOrDays";
        }
        return "Valid";
    }

    /**
     * Get days before SAML cert expires
     *
     * @param string $samlCert Certificate of IDP
     * @param int    $nowTimestamp
     * @return int|null Days before the $samlCert expires, negative if past expiry
     *                         Returns NULL if unable to parse an expiry
     */
    public static function getDaysBeforeExpiry(string $samlCert, int $nowTimestamp): ?int
    {
        $samlCertMetadata = openssl_x509_parse($samlCert);

        if ($samlCertMetadata === false) {
            return null;
        }

        if (array_key_exists('validTo_time_t', $samlCertMetadata)) {
            return ((int)floor(($samlCertMetadata['validTo_time_t'] - $nowTimestamp) / 60 / 60 / 24));
        }
        //returns null if the expiry date cannot be found
        return null;
    }


    /**
     * Arrayify certs from parseSAMLConfig
     *
     * @param array $samlConfig
     * @return array
     */
    public static function getArrayOfSamlCerts(array $samlConfig): array
    {
        /**
         * Replicates library logic to derive cert list.
         * @see \OneLogin\Saml2\Response::isValid()
         * @see \OneLogin\Saml2\Utils::validateSign()
         */
        $out = [];
        if (!empty(($samlConfig['idp']['x509certMulti']['signing']) ?? null)) {
            $out = $samlConfig['idp']['x509certMulti']['signing'];
        } elseif (!empty(($samlConfig['idp']['x509cert']) ?? null)) {
            $out = [$samlConfig['idp']['x509cert']];
        }
        return $out;
    }
}
