#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }


    /** @inheritDoc */
    public function run(): void
    {
        /** @var Config $conf */
        $conf = Config::getInstance();

        $config = array(
            "digest_alg" => "sha256",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );

        $dn = array(
            "countryName" => "AU",
            "stateOrProvinceName" => "Victoria",
            "localityName" => "Melbourne",
            "organizationName" => "Schoolbox Pty Ltd",
            "organizationalUnitName" => "Schoolbox",
            "commonName" =>    $conf::get('site_hostname').'.'.$conf::get('site_domain'),
            "emailAddress" => "support@schoolbox.com.au",
        );

        // Generate a certificate signing request
        $csr = openssl_csr_new($dn, $privkey, $config);

        // Generate a self-signed cert, valid for 3650 days (10 years)
        $x509 = openssl_csr_sign($csr, null, $privkey, $days=3650, array('digest_alg' => 'sha256'));

        // Save your private key, CSR and self-signed cert for later use
        openssl_csr_export($csr, $csrout);
        openssl_x509_export($x509, $certout);
        openssl_pkey_export($privkey, $pkeyout, sha1(strtolower(trim($conf::get('site_school_code')))));

        $conf::set('saml_private_cert',$pkeyout);
        $conf::set('saml_public_cert',$certout);
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();

