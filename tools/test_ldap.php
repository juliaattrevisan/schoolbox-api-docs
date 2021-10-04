#!/usr/bin/php
<?php

/**
 * Usage: ./cleanup.php [-p] [-vvv]
 *
 * A tool that will remove all UPS logs older than 1 month
 *
 *  -p          perform: *without* this flag, will only do a dry run
 *  -v[v[v]]    verbosity: how much activity will be logged
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Monolog\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\App {
    public function run(): void
    {
        $host = $this->getConfig()->get('ldap_host');
        $username = $this->getConfig()->get('ldap_user');
        $password = $this->getConfig()->get('ldap_pass');
        $ldapCaCertHash = $this->getConfig()->get('ldap_ca_certificate');
        $session = new Session;

        ldap_set_option(null, LDAP_OPT_DEBUG_LEVEL, 7);


        if ($host) {
            if ($ldapCaCertHash) {
                // Copy this file to local cache somewhere (stream_dir I guess)
                $ldapCaCert     = StorageMapper::getInstance()->findByHash($ldapCaCertHash);
                $ldapCaCertPath = sprintf(
                    '%s/ldap-ca-cert-%s-%s',
                    Config::get('stream_dir'),
                    INSTANCE_ID,
                    $ldapCaCert->getHash()
                );
                if (!file_exists($ldapCaCertPath)) {
                    file_put_contents($ldapCaCertPath, $ldapCaCert->read());
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
                } else {
                    if (ldap_get_option($ds, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error)) {
                        echo "Error Binding to LDAP: $extended_error";
                    } else {
                        echo "Error Binding to LDAP: No additional information is available.";
                    }
                }
            }
        }
    }
}, [CliTool\Kernel::OPTION_LEVEL => Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
