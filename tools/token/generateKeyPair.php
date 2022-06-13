#!/usr/bin/php
<?php

/**
 * Generate key pairs for JWT.
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Role;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    private const OPT_SHORT_HELP  = 'h';
    private const OPT_LONG_HELP   = 'help';

    private const OPT_SHORT_FORCE = 'f';
    private const OPT_LONG_FORCE  = 'force';

    private const OPT_SHORT_QUIET = 'q';
    private const OPT_LONG_QUIET  = 'quiet';

    /**
     * Short options:
     * * `-f` to force override of existing key
     * * `-h` to get help text
     *
     * @return array
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP, self::OPT_SHORT_FORCE, self::OPT_SHORT_QUIET,];
    }


    /**
     * Long options:
     * * `--force` to force override of existing key
     * * `--help` to get help text
     *
     * @return array
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP, self::OPT_LONG_FORCE, self::OPT_LONG_QUIET,];
    }

    /**
     * Does not touch any files, so user is not important
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /**
     *
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Prepare query
        $db             = $this->getDatabaseInternal();
        $selectQuery    = $db->prepareQuery("SELECT value FROM config WHERE name=:name");
        $updateQuery    = $db->prepareQuery("UPDATE config SET value=:value WHERE name=:name");
        $publicKeyName  = 'jwt_public_key';
        $privateKeyName = 'jwt_private_key';

        $existingPublicKey = null;
        $selectQuery->execute(['name' => $publicKeyName]);
        if ($selectQuery->rowCount() > 0) {
            $existingPublicKey = $selectQuery->fetchColumn();
        }

        $existingPrivateKey = null;
        $selectQuery->execute(['name' => $privateKeyName]);
        if ($selectQuery->rowCount()) {
            $existingPrivateKey = $selectQuery->fetchColumn();
        }

        if (!isset($existingPrivateKey, $existingPublicKey)) {
            $this->getLogger()->warning(
                sprintf(
                    'Cannot create key pair: the configuration options for the key pair (%s, %s) do not exist',
                    $publicKeyName,
                    $privateKeyName
                )
            );
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        if (!($this->getOpt(self::OPT_SHORT_FORCE) || $this->getOpt(self::OPT_LONG_FORCE))) {
            if (!($existingPublicKey === '' && $existingPrivateKey === '')) {
                if (!($this->getOpt(self::OPT_SHORT_QUIET) || $this->getOpt(self::OPT_LONG_QUIET))) {
                    $this->getLogger()->notice(
                        'A key pair already exists. Run with the `-f` option to override the existing key pair.'
                    );
                }
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }
        }

        $keyPair = \Schoolbox\Auth\KeyPairGenerator::generate();

        // and update
        $updateQuery->execute(['value' => $keyPair['public'], 'name' => 'jwt_public_key']);
        $updateQuery->execute(['value' => $keyPair['private'], 'name' => 'jwt_private_key']);
        $this->getLogger()->notice('Key pair successfully generated.');
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
        $file   = $this->getToolFilename();
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $help = <<<HELP

NAME:
    API Token Key Pair Generator

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Generates a public/private key pair for signing/generating a user API token.

    $yellow-h, --help$white
        Print this help and exit

    $yellow-f, --force$white
        Force generation of a new key pair, even if a pair already exists.
        (Takes priority over $yellow-q$white).

        By default: this will refuse to override an existing key pair, and will
        exit with an error code if either the public or private key exists.

    $yellow-q, --quiet$white
        Silently exits without doing anything if a pair already exists, instead
        of outputting a message.

HELP;
        \fwrite(STDOUT, $help);
    }
};

$options = [
    CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
    CliTool\Kernel::OPTION_ERRORS => E_ALL,
];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
