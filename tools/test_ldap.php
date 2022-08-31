#!/usr/bin/php
<?php

/**
 * Usage: ./test_ldap.php
 *
 * This tool will output debug information about connecting to LDAP.  It is useful for debugging SSL certificate
 * issues with LDAPS connections.
 *
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Monolog\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\App {
    public function run(): void
    {
        $this->getLogger()->notice("Testing primary...");
        $this->testLdap($this->getConfig()->get('ldap_host'), $this->getConfig()->get('ldap_user'));

        $this->getLogger()->notice("Testing alternate...");
        $this->testLdap($this->getConfig()->get('ldap_host_alt'),$this->getConfig()->get('ldap_user_alt'));

        $this->getLogger()->notice("Testing tertiary...");
        $this->testLdap($this->getConfig()->get('ldap_host_ter'),$this->getConfig()->get('ldap_user_ter'));
    }

    /**
     * Tests the specified LDAP Host + Username
     *
     * @param string $host
     * @param string $username
     * @return void
     */
    private function testLdap(string $host, string $username): void
    {
        if (empty($host)) {
            $this->getLogger()->notice("Empty host, skipping...");
            return;
        }
        if (empty($username)) {
            $this->getLogger()->notice("Empty username, skipping...");
            return;
        }
        $password = $this->getConfig()->get('ldap_pass');
        $ldapCaCertHash = $this->getConfig()->get('ldap_ca_certificate');
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');
        $session = new Session();

        // This enables the debug output from the LDAP functions
        ldap_set_option(null, LDAP_OPT_DEBUG_LEVEL, 7);

        if ($ldapCaCertHash) {
            // Copy this file to local cache somewhere (stream_dir I guess)
            $ldapCaCert     = StorageMapper::getInstance()->findByHash($ldapCaCertHash);
            $ldapCaCertPath = sprintf(
                '%s/ldap-ca-cert-%s-%s',
                $this->getConfig()->get('stream_dir'),
                INSTANCE_ID,
                $ldapCaCert->getHash()
            );
            if (!file_exists($ldapCaCertPath)) {
                file_put_contents($ldapCaCertPath, $filesystemProxy->read($ldapCaCert));
            }
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTDIR, dirname($ldapCaCertPath));
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $ldapCaCertPath);
        }
        $ds = ldap_connect($host);
        if ($ds) {
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            if (ldap_bind($ds, $session->ldapEscape($username), utf8_encode($password))) {
                $this->getLogger()->info('LDAP Connection Successful');
            } elseif (ldap_get_option($ds, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error)) {
                echo "Error Binding to LDAP: $extended_error";
            } else {
                echo "Error Binding to LDAP: No additional information is available.";
            }
        }
    }
}, [CliTool\Kernel::OPTION_LEVEL => Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
