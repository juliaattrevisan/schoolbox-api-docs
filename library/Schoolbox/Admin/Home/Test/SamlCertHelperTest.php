<?php

/** @noinspection PhpUnhandledExceptionInspection
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Home\Test;

use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit\Framework;
use Schoolbox\Admin\Home\SamlCertHelper;

/**
 * Tests SAML public cert expiry logic
 *
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Admin\Home\SamlCertHelper::class)]
class SamlCertHelperTest extends Framework\TestCase
{
    // Expires 2027-04-26T00:51:48.000Z
    private const METADATA_2027_04_26 = '<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="https://accounts.google.com/o/saml2?idpid=C04d5hemc" validUntil="2027-04-26T00:51:48.000Z">
        <md:IDPSSODescriptor WantAuthnRequestsSigned="false" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:KeyDescriptor use="signing">
        <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <ds:X509Data>
        <ds:X509Certificate>
        MIIDdDCCAlygAwIBAgIGAYBogLrfMA0GCSqGSIb3DQEBCwUAMHsxFDASBgNVBAoTC0dvb2dsZSBJ
        bmMuMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MQ8wDQYDVQQDEwZHb29nbGUxGDAWBgNVBAsTD0dv
        b2dsZSBGb3IgV29yazELMAkGA1UEBhMCVVMxEzARBgNVBAgTCkNhbGlmb3JuaWEwHhcNMjIwNDI3
        MDA1MTQ4WhcNMjcwNDI2MDA1MTQ4WjB7MRQwEgYDVQQKEwtHb29nbGUgSW5jLjEWMBQGA1UEBxMN
        TW91bnRhaW4gVmlldzEPMA0GA1UEAxMGR29vZ2xlMRgwFgYDVQQLEw9Hb29nbGUgRm9yIFdvcmsx
        CzAJBgNVBAYTAlVTMRMwEQYDVQQIEwpDYWxpZm9ybmlhMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A
        MIIBCgKCAQEAvIQ2tTP3w4vpCjArgMLV2YdyFZsT5lUsDgC80NctUCYh7ljKc+Hrqi4GRSLpmgfD
        u3iozFik+UZiP32kfpMN+G6GFTK01BhNIFQNmIX5Bzwtm3XI653n70ZCkVfox2TsrAN3XSIg1FTv
        ZgMNVYtGfnwG2IlaosdVVQCir/gCJ+MobioWw3VrT6I2b2856scMsCljsKwBI6YtywGHwv1eifQv
        dN2AmMv5Nj9kUL6/QW6AHaSQ/bXvxjFVwFX4RGyxoc/uOk+DfY6423SOQe7azWcBliZVz4ms1Gjp
        3NZvCWukxfT5i5bg9dnN1oF84/Q7ENChOHi7tPhEcktFKYksHwIDAQABMA0GCSqGSIb3DQEBCwUA
        A4IBAQCM5OZ137KN4a/PV7FaiL1mIYx+s/GPXdqfGFQbDswnD8bSSQv9FPAGWvr+M+12U03LZ14I
        eQtFGY6u5twD7I5WxXCta3hFJMWDjyPv8jKbIc2c4CzAwInWfaGXMaSxySecgrbL1R8bnxIIYFIm
        3+L4bUTsivmBCID3pR+57z6x/YRh5DT1+rBbXMQvjXLGGCCMisV8QL67yaKBk4Q6CXIC4NhrhTlH
        GtLHNyGqEBquSg66HgTVXPahllMe1gqroS0oNDao3s0Wj/rcEnApNNsIpaK53tc6tDLSRxVqGK6W
        Nf1qqHW6vBAev7bpT+bFZ4gui9f96iGlAYjsWGHU2VcW</ds:X509Certificate>
        </ds:X509Data>
        </ds:KeyInfo>
        </md:KeyDescriptor>
        <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
        <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://accounts.google.com/o/saml2/idp?idpid=C04d5hemc"/>
        <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="https://accounts.google.com/o/saml2/idp?idpid=C04d5hemc"/>
        </md:IDPSSODescriptor>
        </md:EntityDescriptor>';

    // Expires 2027-04-26T00:51:48.000Z
    private const CERT_2027_04_26 = "
        MIIDdDCCAlygAwIBAgIGAYBogLrfMA0GCSqGSIb3DQEBCwUAMHsxFDASBgNVBAoT
        C0dvb2dsZSBJ bmMuMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MQ8wDQYDVQQDEwZ
        Hb29nbGUxGDAWBgNVBAsTD0dv b2dsZSBGb3IgV29yazELMAkGA1UEBhMCVVMxEz
        ARBgNVBAgTCkNhbGlmb3JuaWEwHhcNMjIwNDI3 MDA1MTQ4WhcNMjcwNDI2MDA1M
        TQ4WjB7MRQwEgYDVQQKEwtHb29nbGUgSW5jLjEWMBQGA1UEBxMN TW91bnRhaW4g
        VmlldzEPMA0GA1UEAxMGR29vZ2xlMRgwFgYDVQQLEw9Hb29nbGUgRm9yIFdvcmsx
        CzAJBgNVBAYTAlVTMRMwEQYDVQQIEwpDYWxpZm9ybmlhMIIBIjANBgkqhkiG9w0B
        AQEFAAOCAQ8AMIIBCgKCAQEAvIQ2tTP3w4vpCjArgMLV2YdyFZsT5lUsDgC80Nct
        UCYh7ljKc+Hrqi4GRSLpmgfD u3iozFik+UZiP32kfpMN+G6GFTK01BhNIFQNmIX
        5Bzwtm3XI653n70ZCkVfox2TsrAN3XSIg1FTv ZgMNVYtGfnwG2IlaosdVVQCir/
        gCJ+MobioWw3VrT6I2b2856scMsCljsKwBI6YtywGHwv1eifQv dN2AmMv5Nj9kU
        L6/QW6AHaSQ/bXvxjFVwFX4RGyxoc/uOk+DfY6423SOQe7azWcBliZVz4ms1Gjp3
        NZvCWukxfT5i5bg9dnN1oF84/Q7ENChOHi7tPhEcktFKYksHwIDAQABMA0GCSqGS
        Ib3DQEBCwUA A4IBAQCM5OZ137KN4a/PV7FaiL1mIYx+s/GPXdqfGFQbDswnD8bS
        SQv9FPAGWvr+M+12U03LZ14I eQtFGY6u5twD7I5WxXCta3hFJMWDjyPv8jKbIc2
        c4CzAwInWfaGXMaSxySecgrbL1R8bnxIIYFIm 3+L4bUTsivmBCID3pR+57z6x/Y
        Rh5DT1+rBbXMQvjXLGGCCMisV8QL67yaKBk4Q6CXIC4NhrhTlH GtLHNyGqEBquS
        g66HgTVXPahllMe1gqroS0oNDao3s0Wj/rcEnApNNsIpaK53tc6tDLSRxVqGK6WN
        f1qqHW6vBAev7bpT+bFZ4gui9f96iGlAYjsWGHU2VcW";

    // Expires 2022/09/08
    private const CERT_2022_09_08 = "
        MIIBpDCCAU6gAwIBAgIURz4CRqX+iRipceptsJIGkBTXNTcwDQYJKoZIhvcNAQEL
        BQAwFjEUMBIGA1UEAwwLZXhhbXBsZS5jb20wHhcNMjIwNzIwMTE0MTQyWhcNMjIw
        OTA4MTE0MTQyWjAWMRQwEgYDVQQDDAtleGFtcGxlLmNvbTBcMA0GCSqGSIb3DQEB
        AQUAA0sAMEgCQQDhS93jDy+c5T9dPYaDG7n2cKgyVnMNoobw7vIVuCA77i9NgL0N
        4qIR+KGffpiN59FjxRy+aqRyF6JqK2N8l8EnAgMBAAGjdDByMB0GA1UdDgQWBBS8
        e9bRWc9tYkOnTk8HSs7raWqmwjAfBgNVHSMEGDAWgBS8e9bRWc9tYkOnTk8HSs7r
        aWqmwjAOBgNVHQ8BAf8EBAMCBaAwIAYDVR0lAQH/BBYwFAYIKwYBBQUHAwEGCCsG
        AQUFBwMCMA0GCSqGSIb3DQEBCwUAA0EApy5r6D8I6OwS4krQBbFrXlUifcHiI8QD
        pVD6z4oqU+9WEA7/hJq/8HFbfA7uT7jeOq6LOjgotc6dMUW32o4evw==";

    private const VALID_METADATA_URL = "http://test.schoolbox.cloud";
    private const INVALID_IDP_CERT = "this is an invalid cert";

    /**
     * Used for test cases where the metadata URL is empty, so we do not need to mock specific behaviour
     * @return \Alaress_Saml_Cache (mocked)
     */
    private function getDefaultCache(): \Alaress_Saml_Cache
    {
        return new \Alaress_Saml_Cache();
    }

    /**
     * Mocks the cache's behaviour when a metadata URL is accessible, but we failed to parse its contents
     * @return \Alaress_Saml_Cache (mocked)
     */
    private function getMetadataUnparsableMock(): \Alaress_Saml_Cache
    {
        $return = $this->createMock(\Alaress_Saml_Cache::class);
        $return->method("getStreamContents")
               ->willReturn(
                   "Unparsable metadata cert"
               );

        $samlCache = $this->createMock(\Alaress_Saml_Cache::class);
        $samlCache->method("init")
                  ->willReturn(
                      $return
                  );
        return $samlCache;
    }

    /**
     * Mocks the cache's behaviour when a metadata URL is offline
     * @return \Alaress_Saml_Cache (mocked)
     */
    private function getMetadataOfflineMock(): \Alaress_Saml_Cache
    {
        $return = $this->createMock(\Alaress_Saml_Cache::class);
        $return->method("getStreamContents")
               ->willReturn(
                   null
               );

        $samlCache = $this->createMock(\Alaress_Saml_Cache::class);
        $samlCache->method("init")
                  ->willReturn(
                      $return
                  );
        return $samlCache;
    }

    /**
     * Mocks the cache's behaviour when a valid cert can be returned
     * @return \Alaress_Saml_Cache (mocked)
     */
    private function getMetadataOkMock(): \Alaress_Saml_Cache
    {
        $return = $this->createMock(\Alaress_Saml_Cache::class);
        $return->method("getStreamContents")
               ->willReturn(
                   self::METADATA_2027_04_26
               );

        $samlCache = $this->createMock(\Alaress_Saml_Cache::class);
        $samlCache->method("init")
                  ->willReturn(
                      $return
                  );
        return $samlCache;
    }

    public function testIdpCertEmptyMetadataUrlEmpty(): void
    {
        /* Testing:
         *  IDP Certificate      - Empty
         *  Metadata URL         - Empty
         */
        $samlCertHelper = new SamlCertHelper($this->getDefaultCache());

        $widget = $samlCertHelper->buildWidget("", "");

        self::assertEquals(SamlCertHelper::LIGHT_ALERT, $widget->getLight());
        self::assertEquals('Empty SAML config', $widget->getMessage());
    }

    public function testIdpCertEmptyMetadataUrlOffline(): void
    {
        /* Testing:
         *  IDP Certificate      - Empty
         *  Metadata URL         - Offline
         */
        $samlCertHelper = new SamlCertHelper($this->getMetadataOfflineMock());

        $widget = $samlCertHelper->buildWidget("", self::VALID_METADATA_URL);
        self::assertEquals(SamlCertHelper::LIGHT_ALERT, $widget->getLight());
        self::assertEquals('Metadata URL offline', $widget->getMessage());
    }

    public function testIdpCertEmptyMetadataUrlUnparsable(): void
    {
        /* Testing:
         *  IDP Certificate      - Empty
         *  Metadata URL         - Unparsable
         */
        $samlCertHelper = new SamlCertHelper($this->getMetadataUnparsableMock());

        $widget = $samlCertHelper->buildWidget("", self::VALID_METADATA_URL);
        self::assertEquals(SamlCertHelper::LIGHT_ALERT, $widget->getLight());
        self::assertEquals('Metadata invalid', $widget->getMessage());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIdpCertEmptyMetadataUrlOk')]
    public function testIdpCertEmptyMetadataUrlOk(string $nowOverride, string $light, string $message): void
    {
        /* Testing:
         *  IDP Certificate      - Empty
         *  Metadata URL         - Ok
         */
        $samlCertHelper = new SamlCertHelper($this->getMetadataOkMock());

        $now    = $this->buildNowOverride($nowOverride);

        $widget = $samlCertHelper->buildWidget("", self::VALID_METADATA_URL, $now);
        self::assertEquals($light, $widget->getLight());
        self::assertEquals($message, $widget->getMessage());
    }

    public static function providerIdpCertEmptyMetadataUrlOk(): array
    {
        return [
            ["2027-03-20", SamlCertHelper::LIGHT_SUCCESS, 'Valid'],
            ["2027-03-30", SamlCertHelper::LIGHT_WARNING, 'Expires in 26 days'],
            ["2027-04-23", SamlCertHelper::LIGHT_ALERT, 'Expires in 2 days'],
            ["2027-04-27", SamlCertHelper::LIGHT_ALERT, 'Expired'],
        ];
    }

    public function testIdpCertUnparsableMetadataUrlEmpty(): void
    {
        /* Testing:
         *  IDP Certificate      - Unparsable
         *  Metadata URL         - Empty
         */
        $samlCertHelper = new SamlCertHelper($this->getDefaultCache());

        $widget = $samlCertHelper->buildWidget(self::INVALID_IDP_CERT, "");
        self::assertEquals(SamlCertHelper::LIGHT_ALERT, $widget->getLight());
        self::assertEquals('Certificate invalid', $widget->getMessage());
    }


    public function testIdpCertUnparsableMetadataUrlOffline(): void
    {
        /* Testing:
         *  IDP Certificate      - Unparsable
         *  Metadata URL         - Offline
         */
        $samlCertHelper = new SamlCertHelper($this->getMetadataOfflineMock());

        $widget = $samlCertHelper->buildWidget(self::INVALID_IDP_CERT, self::VALID_METADATA_URL);
        self::assertEquals($samlCertHelper::LIGHT_ALERT, $widget->getLight());
        self::assertEquals('Certificate invalid. Metadata URL offline', $widget->getMessage());
    }


    public function testIdpCertUnparsableMetadataUrlUnparsable(): void
    {
        /* Testing:
         *  IDP Certificate      - Unparsable
         *  Metadata URL         - Unparsable
         */
        $samlCertHelper = new SamlCertHelper($this->getMetadataUnparsableMock());

        $widget = $samlCertHelper->buildWidget(self::INVALID_IDP_CERT, self::VALID_METADATA_URL);
        self::assertEquals(SamlCertHelper::LIGHT_ALERT, $widget->getLight());
        self::assertEquals('Certificate invalid. Metadata invalid', $widget->getMessage());
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('providerIdpCertUnparsableMetadataUrlOk')]
    public function testIdpCertUnparsableMetadataUrlOk(string $nowOverride, string $light, string $message): void
    {
        /* Testing:
         *  IDP Certificate      - Unparsable
         *  Metadata URL         - Ok
         */
        $now    = $this->buildNowOverride($nowOverride);

        $samlCertHelper = new SamlCertHelper($this->getMetadataOkMock());

        $widget = $samlCertHelper->buildWidget(self::INVALID_IDP_CERT, self::VALID_METADATA_URL, $now);

        self::assertEquals($light, $widget->getLight());
        self::assertEquals($message, $widget->getMessage());
    }

    public static function providerIdpCertUnparsableMetadataUrlOk(): array
    {
        return [
            ["2027-03-20", SamlCertHelper::LIGHT_SUCCESS, "Valid"],
            ["2027-03-30", SamlCertHelper::LIGHT_WARNING, "Expires in 26 days"],
            ["2027-04-23", SamlCertHelper::LIGHT_ALERT, "Expires in 2 days"],
            ["2027-04-27", SamlCertHelper::LIGHT_ALERT, "Expired"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIdpCertOkMetadataUrlEmpty')]
    public function testIdpCertOkMetadataUrlEmpty(string $nowOverride, string $light, string $message): void
    {
        /* Testing:
         *  IDP Certificate      - Ok
         *  Metadata URL         - Empty
         */
        $now    = $this->buildNowOverride($nowOverride);

        $samlCertHelper = new SamlCertHelper($this->getDefaultCache());

        $widget = $samlCertHelper->buildWidget(self::CERT_2027_04_26, "", $now);
        self::assertEquals($light, $widget->getLight());
        self::assertEquals($message, $widget->getMessage());
    }

    public static function providerIdpCertOkMetadataUrlEmpty(): array
    {
        return [
            ["2027-03-20", SamlCertHelper::LIGHT_SUCCESS, "Valid"],
            ["2027-03-30", SamlCertHelper::LIGHT_WARNING, "Expires in 26 days"],
            ["2027-04-23", SamlCertHelper::LIGHT_ALERT, "Expires in 2 days"],
            ["2027-04-27", SamlCertHelper::LIGHT_ALERT, "Expired"],
        ];
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('providerIdpCertOkMetadataUrlOffline')]
    public function testIdpCertOkMetadataUrlOffline(string $nowOverride, string $light, string $message): void
    {
        /* Testing:
         *  IDP Certificate      - Ok
         *  Metadata URL         - Offline
         */
        $samlCertHelper = new SamlCertHelper($this->getMetadataOfflineMock());

        $now    = $this->buildNowOverride($nowOverride);

        $widget = $samlCertHelper->buildWidget(self::CERT_2027_04_26, self::VALID_METADATA_URL, $now);
        self::assertEquals($light, $widget->getLight());
        self::assertEquals($message, $widget->getMessage());
    }

    public static function providerIdpCertOkMetadataUrlOffline(): array
    {
        return [
            ["2027-03-20", SamlCertHelper::LIGHT_SUCCESS, "Valid"],
            ["2027-03-30", SamlCertHelper::LIGHT_WARNING, "Expires in 26 days"],
            ["2027-04-23", SamlCertHelper::LIGHT_ALERT, "Expires in 2 days"],
            ["2027-04-27", SamlCertHelper::LIGHT_ALERT, "Expired"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIdpCertOkMetadataUrlUnparsable')]
    public function testIdpCertOkMetadataUrlUnparsable(string $nowOverride, string $light, string $message): void
    {
        /* Testing:
         *  IDP Certificate      - Ok
         *  Metadata URL         - Unparsable
         */
        $samlCertHelper = new SamlCertHelper($this->getMetadataUnparsableMock());

        $now    = $this->buildNowOverride($nowOverride);

        $widget = $samlCertHelper->buildWidget(self::CERT_2027_04_26, self::VALID_METADATA_URL, $now);
        self::assertEquals($light, $widget->getLight());
        self::assertEquals($message, $widget->getMessage());
    }

    public static function providerIdpCertOkMetadataUrlUnparsable(): array
    {
        return [
            ["2027-03-20", SamlCertHelper::LIGHT_SUCCESS, "Valid"],
            ["2027-03-30", SamlCertHelper::LIGHT_WARNING, "Expires in 26 days"],
            ["2027-04-23", SamlCertHelper::LIGHT_ALERT, "Expires in 2 days"],
            ["2027-04-27", SamlCertHelper::LIGHT_ALERT, "Expired"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIdpCertOkMetadataUrlOk')]
    public function testIdpCertOkMetadataUrlOk(string $nowOverride, string $idpCert, string $light, string $message): void
    {
        /* Testing:
         *  IDP Certificate      - Ok
         *  Metadata URL         - Ok
         */
        $samlCertHelper = new SamlCertHelper($this->getMetadataOkMock());

        $now    = $this->buildNowOverride($nowOverride);

        $widget = $samlCertHelper->buildWidget($idpCert, self::VALID_METADATA_URL, $now);
        self::assertEquals($light, $widget->getLight());
        self::assertEquals($message, $widget->getMessage());
    }

    public static function providerIdpCertOkMetadataUrlOk(): array
    {
        return [
            ["2027-03-20", self::CERT_2022_09_08, SamlCertHelper::LIGHT_SUCCESS, 'Valid'],
            ["2027-03-20", self::CERT_2027_04_26, SamlCertHelper::LIGHT_SUCCESS, 'Valid'],
            ["2027-03-30", self::CERT_2022_09_08, SamlCertHelper::LIGHT_WARNING, 'Expires in 26 days'],
            ["2027-03-30", self::CERT_2027_04_26, SamlCertHelper::LIGHT_WARNING, 'Expires in 26 days'],
            ["2027-04-23", self::CERT_2022_09_08, SamlCertHelper::LIGHT_ALERT, 'Expires in 2 days'],
            ["2027-04-23", self::CERT_2027_04_26, SamlCertHelper::LIGHT_ALERT, 'Expires in 2 days'],
            ["2027-04-27", self::CERT_2022_09_08, SamlCertHelper::LIGHT_ALERT, 'Expired'],
            ["2027-04-27", self::CERT_2027_04_26, SamlCertHelper::LIGHT_ALERT, 'Expired'],
        ];
    }

    /**
     * Given a now override date string in YYYY-MM-DD format, creates a date
     * object for the end of that day in the UTC timezone.
     *
     * @param string $nowOverride a YYYY-MM-DD string
     * @return Alaress_Date_Format
     */
    private function buildNowOverride(string $nowOverride): Alaress_Date_Format
    {
        $now = new Alaress_Date_Format(
            $nowOverride,
            Alaress_Date_Format::DATABASE_DATE_FORMAT,
            new DateTimeZone('UTC'),
        );
        return $now->endOfDay();
    }
}
