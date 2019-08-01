#!/usr/bin/php
<?php
/**
 * createKeys
 *
 * Regenerates saml certificates which are stored in config table
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * Self-signed cert valid for this many days
     * Default is 3650 days (10 years)
     */
    private const DURATION = 3650;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Regenerate SAML certificates for saml_private_cert and saml_public_cert fields in config table.

Usage: createKeys.php


HELP;
            exit;
        }

        /** @var Config $conf */
        $conf = Config::getInstance();

        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $dn = [
            'countryName' => 'AU',
            'stateOrProvinceName' => 'Victoria',
            'localityName' => 'Melbourne',
            'organizationName' => 'Schoolbox Pty Ltd',
            'organizationalUnitName' => 'Schoolbox',
            'commonName' =>    $conf::get('site_hostname').'.'.$conf::get('site_domain'),
            'emailAddress' => 'support@schoolbox.com.au',
        ];

        // Generate a certificate signing request
        $csr = openssl_csr_new($dn, $privkey, $config);

        // Generate a self-signed cert, valid for 3650 days (10 years)
        $x509 = openssl_csr_sign($csr, null, $privkey, self::DURATION, ['digest_alg' => 'sha256']);

        // Save your private key, CSR and self-signed cert for later use
        openssl_csr_export($csr, $csrout);
        openssl_x509_export($x509, $certout);
        openssl_pkey_export($privkey, $pkeyout, sha1(strtolower(trim($conf::get('site_school_code')))));

        $conf::set('saml_private_cert', $pkeyout);

        $this->getLogger()->info('saml_private_cert was updated.');

        $conf::set('saml_public_cert', $certout);

        $this->getLogger()->info('saml_public_cert was updated.');
    }
};

(new CliTool\Kernel($app))->runApp();
