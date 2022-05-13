<?php

namespace Schoolbox\Admin\Home;

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
     * @return float  Days before the $cert expires,
     *                negative if past expiry
     */
    public static function getDaysBeforeExpiry(string $samlCert): ?float
    {
        $validTo = openssl_x509_parse($samlCert)['validTo_time_t'];
        return ($validTo - time()) / 60 / 60 / 24;
    }

    /**
     * Get SAML cert for an instance
     *
     * @return string|null  SAML certificate
     */
    public static function getSamlCertFromMetadataUrl($url): ?string
    {
        $samlCert = '';
        try {
            $url = new \Laminas\Uri\Uri($url);
            $idpMetadataUrl = $url->toString();

            $cache = new \Alaress_Saml_Cache();
            $idpMetadataCache = $cache->init($idpMetadataUrl);

            // if cache exists, update $setting info from cache
            if (!empty($idpMetadataCache->getStreamContents()) && file_exists($idpMetadataCache->getCachedFile())) {
                $samlCert = "-----BEGIN CERTIFICATE-----\n";

                try {
                    $samlCert.= IdPMetadataParser::parseXML($idpMetadataCache->getStreamContents())['idp']['x509cert'];
                } catch (\Exception $e) {
                    $cache->invalidateCache();
                }

                $samlCert.= "\n-----END CERTIFICATE-----";
            }
        } catch (\Exception $e) {
            //noop, metadata url invalid
        }
        return $samlCert;
    }
}
