<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace SocialStream\Cache;

use Schoolbox\Resource\Storage\RelationalDelete\Nullable;

class RelationalDelete extends Nullable
{
    /**
     * @var string
     */
    protected $hashColumn = 'cache_hash';

    /**
     * @var array
     */
    protected $setMethods = ['setCacheHash'];
}
