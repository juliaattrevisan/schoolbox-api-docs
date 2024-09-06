<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External;

/**
 * Provides a connection to an external SIS system
 */
interface IConnector
{
    // All external_types
    public const EXTERNAL_TYPE_SYNERGETIC  = 'synergetic';
    public const EXTERNAL_TYPE_TASS        = 'tass';
    public const EXTERNAL_TYPE_EDUMATE     = 'edumate';
    public const EXTERNAL_TYPE_SCHOOLBOX   = 'schoolbox';
    public const EXTERNAL_TYPE_SCHOOLPRO   = 'schoolpro';
    public const EXTERNAL_TYPE_PCSCHOOLS   = 'pcschools';
    public const EXTERNAL_TYPE_DOUBLEFIRST = 'doublefirst';
    public const EXTERNAL_TYPE_SCHOOLEDGE  = 'schooledge';
    public const EXTERNAL_TYPE_ISAMS       = 'isams';

    public const EXTERNAL_TYPES = [
        self::EXTERNAL_TYPE_SYNERGETIC,
        self::EXTERNAL_TYPE_TASS,
        self::EXTERNAL_TYPE_EDUMATE,
        self::EXTERNAL_TYPE_SCHOOLBOX,
        self::EXTERNAL_TYPE_SCHOOLPRO,
        self::EXTERNAL_TYPE_PCSCHOOLS,
        self::EXTERNAL_TYPE_DOUBLEFIRST,
        self::EXTERNAL_TYPE_SCHOOLEDGE,
        self::EXTERNAL_TYPE_ISAMS,
    ];
}
