<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Item;

use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;
use Schoolbox\Resource\Storage\GarbageCollector\SoftLink;

class GarbageCollector extends SoftLink implements IGarbageCollector
{
    /**
     * @var string
     */
    protected $hashColumn = 'image_hash';

    /**
     * @var array
     */
    protected $setMethods = ['setImage'];
}
