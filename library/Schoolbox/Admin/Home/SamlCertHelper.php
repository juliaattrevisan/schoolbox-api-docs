<?php

namespace Schoolbox\Admin\Home;

use OneLogin\Saml2\IdPMetadataParser;

use function PHPUnit\Framework\arrayHasKey;

/**
 * Functions for processing SAML certificate
 */
class SamlCertHelper
{
    /**
     * Get days before SAML cert expires
     *
     * @param string $samlCert Certificate of IDP
     * @return int  Days before the $cert expires,
     *                negative if past expiry
     */
    public static function getDaysBeforeExpiry(string $samlCert): ?int
    {
        $samlCertMetadata = @openssl_x509_parse($samlCert);

        if (!is_array($samlCertMetadata)) {
            return null;
        }

        if (array_key_exists('validTo_time_t', $samlCertMetadata)) {
            return ((int) floor(($samlCertMetadata['validTo_time_t'] - time()) / 60 / 60 / 24));
        }
        //returns null if the expiry date cannot be found
        return null;
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
                $idpMetadata = [];
                try {
                    $idpMetadata = IdPMetadataParser::parseXML($idpMetadataCache->getStreamContents());
                } catch (\Exception $e) {
                    $cache->invalidateCache();
                }

                //find the value in the array that is valid for the XML doc structure
                $samlCert = $idpMetadata['idp']['x509cert'] ?? '';

                if ($samlCert === '') {
                    //special case found via LGS
                    $samlCert = $idpMetadata['idp']['x509certMulti']['signing'][1] ?? '';
                }

                $samlCert = "-----BEGIN CERTIFICATE-----\n" . $samlCert . "\n-----END CERTIFICATE-----";
            }
        } catch (\Exception $e) {
            //noop, metadata url invalid
        }
        return $samlCert;
    }
}
