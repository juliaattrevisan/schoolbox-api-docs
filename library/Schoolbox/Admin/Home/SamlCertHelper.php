<?php

namespace Schoolbox\Admin\Home;

use Alaress\Exception\UnreachableException;
use Alaress\Http\Cache;
use Alaress_Date_Format;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Schoolbox\Url\Uri;
use OneLogin\Saml2\IdPMetadataParser;
use OneLogin\Saml2\Utils;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;
use Schoolbox\Application\Exception as SchoolboxException;

/**
 * Functions for processing SAML certificate
 */
class SamlCertHelper
{
    public const SAML_CACHE_TIMEOUT = 3600;
    public const SAML_CACHE_PREFIX  = 'saml_';

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

    /**
     * Constructor
     *
     * @param Cache $samlCache The HTTP client cache from which SAML config is fetched.
     */
    public function __construct(private Cache $samlCache)
    {
    }

    /**
     * @param string                   $idpCertificate
     * @param string                   $idpMetadataUrl
     * @param Alaress_Date_Format|null $nowOverride Enable 'now' to be overridden for deterministic unit testing.
     * @return SamlWidget
     */
    public function buildWidget(
        string $idpCertificate,
        string $idpMetadataUrl,
        ?Alaress_Date_Format $nowOverride = null,
    ): SamlWidget {
        /*
         * Time value used in testing to simulate and reproduce different test cases.
         * We use this to replicate how the widget responds depending on how close a cert is to expiry.
         */
        $now = $nowOverride ?? Alaress_Date_Format::now();

        // Derive idp certificate status + daysToExpire
        if (trim($idpCertificate) === "") {
            $idpCertDaysToExpiry = null;
            $idpCertStatus       = self::IDP_CERT_EMPTY;
        } else {
            $idpCertDaysToExpiry = $this->getDaysBeforeExpiry(Utils::formatCert($idpCertificate), $now->getUnixTimeStamp());
            $idpCertStatus       = $idpCertDaysToExpiry === null ? self::IDP_CERT_UNPARSABLE : self::IDP_CERT_OK;
        }

        // Derive metadata URL status + daysToExpire
        if (trim($idpMetadataUrl) === "") {
            // Empty metadata URL
            $metadataUrlStatus    = self::METADATA_URL_EMPTY;
            $metadataDaysToExpire = null;
        } else {
            try {
                $metadataXml = $this->fetchMetadataXml($idpMetadataUrl);
            } catch (InvalidArgumentException | UnreachableException | SchoolboxException\Exception $e) {
                // Invalid URL | Unable to connect.
                $metadataXml = null;
            }

            if ($metadataXml === null) {
                $metadataDaysToExpire = null;
                $metadataUrlStatus    = self::METADATA_URL_OFFLINE;
            } else {
                $metadataDaysToExpire = $this->parseDaysToExpireFromMetadataXml($metadataXml, $now);
                $metadataUrlStatus    = $metadataDaysToExpire === null
                    ? self::METADATA_URL_UNPARSABLE
                    : self::METADATA_URL_OK;
            }
        }

        return $this->buildStatusFromDerived($idpCertStatus, $idpCertDaysToExpiry, $metadataUrlStatus, $metadataDaysToExpire);
    }


    /**
     * Returns XML metadata content from the specified URL.
     *
     * @param string $idpMetadataUrl
     * @return string
     * @throws UnreachableException if caching failed for some reason
     */
    private function fetchMetadataXml(string $idpMetadataUrl): string
    {
        try {
            $url = new Uri($idpMetadataUrl);
        } catch (InvalidArgumentException $e) {
            // Should never get here. Only thrown in $idpMetadataUrl is not a string, which is
            // impossible due to method's param type declaration.
            throw new UnreachableException($e);
        }

        $validUrl = (string)$url;

        try {
            return $this->samlCache->request($validUrl, self::SAML_CACHE_PREFIX, [], self::SAML_CACHE_TIMEOUT);
        } catch (InvalidArgumentException | GuzzleException | CacheInvalidArgumentException $e) {
            // Should never get here. Only thrown in $idpMetadataUrl is not a string, which is
            // impossible due to method's param type declaration.
            throw new UnreachableException($e);
        }
    }


    /**
     * Returns number of days to expiry.
     * Returns NULL if unable to parse/derive a value.
     *
     * @param string              $metadataXml
     * @param Alaress_Date_Format $now
     * @return int|null
     */
    private function parseDaysToExpireFromMetadataXml(string $metadataXml, Alaress_Date_Format $now): ?int
    {
        try {
            $metadataInfo = IdPMetadataParser::parseXML($metadataXml);
        } catch (\Exception $e) {
            // Unable to parse XML
            return null;
        }

        $certExpiryArray = [];
        foreach ($this->getArrayOfSamlCerts($metadataInfo) as $cert) {
            $formattedCert    = Utils::formatCert($cert);
            $daysBeforeExpiry = $this->getDaysBeforeExpiry($formattedCert, $now->getUnixTimeStamp());
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
    private function buildStatusFromDerived(int $idpCertStatus, ?int $idpCertDaysToExpiry, int $metadataUrlStatus, ?int $metadataDaysToExpire): SamlWidget
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
            return new SamlWidget($this->getLight($metadataDaysToExpire), $this->getMessage($metadataDaysToExpire));
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
            return new SamlWidget($this->getLight($metadataDaysToExpire), $this->getMessage($metadataDaysToExpire));
        }

        if ($idpCertStatus === self::IDP_CERT_OK && $metadataUrlStatus === self::METADATA_URL_EMPTY) {
            return new SamlWidget($this->getLight($idpCertDaysToExpiry), $this->getMessage($idpCertDaysToExpiry));
        }

        if ($idpCertStatus === self::IDP_CERT_OK && $metadataUrlStatus === self::METADATA_URL_OFFLINE) {
            return new SamlWidget($this->getLight($idpCertDaysToExpiry), $this->getMessage($idpCertDaysToExpiry));
        }

        if ($idpCertStatus === self::IDP_CERT_OK && $metadataUrlStatus === self::METADATA_URL_UNPARSABLE) {
            return new SamlWidget($this->getLight($idpCertDaysToExpiry), $this->getMessage($idpCertDaysToExpiry));
        }

        // $idpCertStatus === self::IDP_CERT_OK && $metadataUrlStatus === self::METADATA_URL_OK
        return new SamlWidget($this->getLight($metadataDaysToExpire), $this->getMessage($metadataDaysToExpire));
    }


    /**
     * Returns what HTML class to apply depending on how many days until the cert expires.
     *
     * @param int $daysToExpire
     * @return string
     */
    private function getLight(int $daysToExpire): string
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
    private function getMessage(int $daysToExpire): string
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
    public function getDaysBeforeExpiry(string $samlCert, int $nowTimestamp): ?int
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
    public function getArrayOfSamlCerts(array $samlConfig): array
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
