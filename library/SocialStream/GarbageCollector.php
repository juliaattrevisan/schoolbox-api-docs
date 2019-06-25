<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace SocialStream;

use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;
use Schoolbox\Resource\Storage\GarbageCollector\SoftLink;

class GarbageCollector extends SoftLink implements IGarbageCollector
{
    protected $hashColumn = 'cache_hash';

    protected $setMethods = ['setCacheHash', 'setThumbnail', 'setUrl'];
}
