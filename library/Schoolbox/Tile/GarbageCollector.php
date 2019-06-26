<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Tile;

use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;
use Schoolbox\Resource\Storage\GarbageCollector\SoftLink;

class GarbageCollector extends SoftLink implements IGarbageCollector
{
    /**
     * @var string
     */
    protected $hashColumn = 'background_image_hash';

    /**
     * @var array
     */
    protected $setMethods = ['setBackgroundImage'];
}
