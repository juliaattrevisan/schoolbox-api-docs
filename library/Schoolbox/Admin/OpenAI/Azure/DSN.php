<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Azure;

use DomainException;
use Schoolbox\Admin\OpenAI\IDSN;
use SensitiveParameter;

class DSN implements IDSN
{
    /**
     * DSN format
     *
     * Product Module (e.g. Student Details API, Identity Management API, LMS Integration API)
     * followed by a colon
     * followed by key-value pairs
     * separated by comma
     */
    private const DSN_FORMAT = '/([^:]+):key=([^;]*);resourcename=([^;]*);deploymentid=([^;,]*)/';


    /**
     * This character separates module-related tuples from each other
     */
    private const MODULE_SEPARATOR = ',';

    /**
     * The expected key-value tuples per module
     * At the moment we expect three keys which should have three values
     */
    private const EXPECTED_TUPLE = ['key', 'resourcename', 'deploymentid'];


    /**
     * @inheritDoc
     */
    public function decode(#[SensitiveParameter] string $dsn): array
    {
        $dsnParts = explode(self::MODULE_SEPARATOR, $dsn);

        $struct = [
            'modules' => [],
        ];

        /* Break apart module config
         */
        foreach ($dsnParts as $dsnPart) {
            $matches = [];
            preg_match(self::DSN_FORMAT, $dsnPart, $matches);

            $suppliedPieces = array_filter([
                $matches[2] ?? null,
                $matches[3] ?? null,
                $matches[4] ?? null
            ]);

            /* Check the dsn format matches correctly
             */
            if (!count($suppliedPieces)) {
                throw new DomainException('Azure AI DSN: could not decode; missing all credentials/bad format. Check openai key, resource name and deployment id');
            }

            /* Check that each module comes with the expected number of key-value pairs
             *
             * original dsn string + module + \count(expected_tuple) = 1 + 1 + 3 = 5
             */
            if (count($matches) !== count(self::EXPECTED_TUPLE) + 2) {
                throw new DomainException('Azure AI DSN: could not decode; missing some credentials. Check openai key, resource name and deployment id');
            }

            $struct['modules'][$matches[1]] = [
                'key' => $matches[2],
                'resourcename' => $matches[3],
                'deploymentid' => $matches[4]
            ];
        }

        return $struct;
    }

    /**
     * @inheritDoc
     */
    public function encode(#[SensitiveParameter] array $parts): string
    {
        // TODO: Implement encode() method.
    }
}
