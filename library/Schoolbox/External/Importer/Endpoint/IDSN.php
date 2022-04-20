<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

interface IDSN
{

    /**
     * Take a DSN string and break it down into its constituent parts
     *
     * @param string $dsn
     * @return array
     */
    public function decode(string $dsn): array;


    /**
     * Take an array of DSN parts and assemble it into a serialized DSN string equivalent
     *
     * @param array $parts
     * @return string
     */
    public function encode(array $parts): string;
}
