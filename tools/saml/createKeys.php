#!/usr/bin/php
<?php

use Aura\Di;

require_once __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');

// Setup kernel

$kernel = new Schoolbox\Application\Daemon\Kernel('Create SAML Certificates');

// Get this kernel started

$kernel->run(function (Di\Container $di, $loop, $log, $opts) {

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
        "organizationName" => "Alaress Pty Ltd",
        "organizationalUnitName" => "Schoolbox",
        "commonName" =>    $conf->get('site_hostname').'.'.$conf->get('site_domain'),
        "emailAddress" => "support@schoolbox.com.au",
    );

    // Generate a certificate signing request
    $csr = openssl_csr_new($dn, $privkey, $config);

    // Generate a self-signed cert, valid for 3650 days (10 years)
    $x509 = openssl_csr_sign($csr, null, $privkey, $days=3650, array('digest_alg' => 'sha256'));

    // Save your private key, CSR and self-signed cert for later use
    openssl_csr_export($csr, $csrout);
    openssl_x509_export($x509, $certout);
    openssl_pkey_export($privkey, $pkeyout, "mypassword");

    $conf->set('saml_private_cert',$pkeyout);
    $conf->set('saml_public_cert',$certout);
});