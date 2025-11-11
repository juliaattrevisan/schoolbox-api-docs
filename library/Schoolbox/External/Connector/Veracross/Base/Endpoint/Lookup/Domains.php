<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

class Domains
{
    /**
     * Veracross configuration across the app requires logging in and accessing domains in the same region.
     * You can't access Global domains via the Australia auth host and vice versa.
     */
    public const array AVAILABLE = [
        self::REGION_AUSTRALIA => [
            'auth' => 'https://accounts.veracross.au',
            'attendance' => 'https://portals.veracross.au',
        ],
        self::REGION_GLOBAL => [
            'auth' => 'https://accounts.veracross.com',
            'attendance' => 'https://portals.veracross.com',
        ],
    ];


    /**
     * A default region in case the one you selected was no longer valid
     */
    public const string DEFAULT_REGION = self::REGION_GLOBAL;


    /**
     * We only have to regions at the moment
     * Add more to the stack as we encounter them
     */
    private const string REGION_AUSTRALIA = 'Australia';
    private const string REGION_GLOBAL = 'Global';
}
