#!/usr/bin/php
<?php
/**
 * Generate key pairs for JWT.
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        // Prepare query
        $db          = $this->getDatabaseInternal();
        $updateQuery = $db->prepareQuery("UPDATE config SET value=:value WHERE name=:name");

        // Build key pair
        $res = openssl_pkey_new([
            'digest_alg'       => 'SHA512',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $privateKey);
        $keyDetails = openssl_pkey_get_details($res);
        $publicKey  = $keyDetails['key'];

        // and update
        $updateQuery->execute(['value' => $publicKey, 'name' => 'jwt_public_key']);
        $updateQuery->execute(['value' => $privateKey, 'name' => 'jwt_private_key']);
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
