#!/usr/bin/env php
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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MapperFactory;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\BaseApp
{
    /**
     * Self-signed cert valid for this many days
     * Default is 3650 days (10 years)
     */
    private const DURATION = 3650;

    /**
     * Hashing algorithm
     */
    private const DIGEST_ALGORITHM = 'sha256';

    protected function getName(): string
    {
        return 'createKeys';
    }

    protected function getDescription(): string
    {
        return 'Regenerates SAML certificates for saml_private_cert and saml_public_cert fields in config table.';
    }

    protected function setOptions(): self
    {
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return false;
    }

    /**
     * Does not touch any files, so user is not important
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritDoc */
    public function run(): void
    {
        $conf   = $this->getConfig();
        $config = [
            'digest_alg' => self::DIGEST_ALGORITHM,
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $dn = [
            'countryName' => 'AU',
            'stateOrProvinceName' => 'Victoria',
            'localityName' => 'Melbourne',
            'organizationName' => 'Schoolbox Pty Ltd',
            'organizationalUnitName' => 'Schoolbox',
            'commonName' =>    $conf->get('site_hostname') . '.' . $conf->get('site_domain'),
            'emailAddress' => 'support@schoolbox.com.au',
        ];

        // Generate a certificate signing request
        $csr = openssl_csr_new($dn, $privkey, $config);

        // Generate a self-signed cert, valid for 3650 days (10 years)
        $x509 = openssl_csr_sign($csr, null, $privkey, self::DURATION, ['digest_alg' => self::DIGEST_ALGORITHM]);

        // Save your private key, CSR and self-signed cert for later use
        openssl_csr_export($csr, $csrout);
        openssl_x509_export($x509, $certout);
        openssl_pkey_export($privkey, $pkeyout, sha1(strtolower(trim($conf->get('site_school_code')))));

        // Need to set config using the mapper
        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');

        /** @var ConfigMapper $configMapper */
        $configMapper = $mapperFactory->get(ConfigMapper::class);

        $samlPrivate = $configMapper->findOneByColumn('name', 'saml_private_cert');
        $samlPrivate->setValue($pkeyout);
        $configMapper->save($samlPrivate);
        $this->getLogger()->info('saml_private_cert was updated.');

        $samlPublic = $configMapper->findOneByColumn('name', 'saml_public_cert');
        $samlPublic->setValue($certout);
        $configMapper->save($samlPublic);
        $this->getLogger()->info('saml_public_cert was updated.');
    }
};

// Note this script needs to bypass the maintenance mode check because it is run from upgrade-instances.sh.
(new CliTool\Kernel($app, [], true))->runApp();
