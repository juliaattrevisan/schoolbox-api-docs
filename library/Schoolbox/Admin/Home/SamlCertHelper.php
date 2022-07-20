<?php

namespace Schoolbox\Admin\Home;

use Alaress_Saml_Cache;
use Exception;
use OneLogin\Saml2\IdPMetadataParser;

/**
 * Functions for processing SAML certificate
 */
class SamlCertHelper
{
    /**
     * Get days before SAML cert expires
     *
     * @param string $samlCert Certificate of IDP
     * @return int|null Days before the $samlCert expires, negative if past expiry
     */
    public static function getDaysBeforeExpiry(string $samlCert): ?int
    {
        $samlCertMetadata = openssl_x509_parse($samlCert);

        if ($samlCertMetadata === false) {
            return null;
        }

        if (array_key_exists('validTo_time_t', $samlCertMetadata)) {
            return ((int) floor(($samlCertMetadata['validTo_time_t'] - time()) / 60 / 60 / 24));
        }
        //returns null if the expiry date cannot be found
        return null;
    }

    /**
     * Parse SAML config and build configuration info array
     *
     * Replicates the SAML parsing present in the product
     *
     * @param string $saml_idp_certificate
     * @param string $saml_idp_metadata_url
     * @return array
     */
    public static function parseSamlConfig(string $saml_idp_certificate, string $saml_idp_metadata_url): array
    {
        // TODO merge this and saml-setup.php into common implementation. See IST-1773
        $settingsInfo = [
            'idp' => [
                'x509cert' => preg_replace("/\r/m", "", $saml_idp_certificate),
            ],
        ];
        try {
            $url = new \Laminas\Uri\Uri($saml_idp_metadata_url);
            $idpMetadataUrl = $url->toString();

            $cache = new Alaress_Saml_Cache();
            $idpMetadata = $cache->init($idpMetadataUrl);

            // if cache exists, update $setting info from cache
            /** @noinspection NotOptimalIfConditionsInspection */
            if (file_exists($idpMetadata->getCachedFile()) && !empty($idpMetadata->getStreamContents())) {
                try {
                    $settingsInfo = IdPMetadataParser::injectIntoSettings($settingsInfo, IdPMetadataParser::parseXML($idpMetadata->getStreamContents()));
                } catch (Exception $e) {
                    $cache->invalidateCache();
                }
            }
        } catch (Exception $e) {
            //noop, metadata url invalid
        }
        return $settingsInfo;
    }

    /**
     * Arrayify certs from parseSAMLConfig
     *
     * @param array $samlConfig
     * @return array
    */
    public static function getArrayOfSamlCerts(array $samlConfig): array
    {
        $out = [];
        if (isset($samlConfig['idp']['x509certMulti'])) {
            $out = $samlConfig['idp']['x509certMulti']['signing'];
        } elseif (isset($samlConfig['idp']['x509cert'])) {
            $out = [$samlConfig['idp']['x509cert']];
        }
        return $out;
    }
}
