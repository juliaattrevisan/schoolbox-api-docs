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
     * Get SAML cert/s from metadata url
     *
     * @param string $url
     * @return array SAML certificates found
     */
    public static function getSamlCertsFromMetadataUrl(string $url): ?array
    {
        $certsOut = null;
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

                if (array_key_exists('x509cert', $idpMetadata['idp'])) {
                    $certsOut = array(self::buildCertString($idpMetadata['idp']['x509cert']));
                } elseif (array_key_exists('x509certMulti', $idpMetadata['idp'])) {
                    $certsOut = [];
                    foreach ($idpMetadata['idp']['x509certMulti']['signing'] as $certInner) {
                        $certsOut[] = self::buildCertString($certInner);
                    }
                }
            }
        } catch (\Exception $e) {
            //noop, metadata url invalid
        }
        return $certsOut;
    }

    /**
     * Find the cert in an array that contains has the most days before expiry
     *
     * @param array $certs array of certs to be checked
     * @return string|null  SAML certificate
     */
    public static function getCertWithLongestDatedExpiryFromX509CertMulti(array $certs): ?string
    {
        $bestCertIndex = -1;
        $bestCertExpiry = -1;
        foreach ($certs as $index => $cert) {
            $currentCertExpiry = self::getDaysBeforeExpiry($cert);
            if ($currentCertExpiry > $bestCertExpiry) {
                $bestCertIndex = $index;
                $bestCertExpiry = $currentCertExpiry;
            }
        }
        return $certs[$bestCertIndex] ?? null;
    }

    /**
     * Add 'begin cert' and 'end cert' onto the provided string
     *
     * @param string $certInner
     * @return string  SAML certificate
     */
    private static function buildCertString(string $certInner): string
    {
        return "-----BEGIN CERTIFICATE-----\n" . $certInner . "\n-----END CERTIFICATE-----";
    }
}
