<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;

use Psr\Log\LoggerInterface;
use Schoolbox\File;
use Schoolbox\Resource\Storage;

interface CacheableLoggerInterface extends LoggerInterface
{

    /**
     * Flush your internal cache to storage
     *
     * @param string $basename
     * @throws File\Exception
     * @return Storage\Model
     */
    public function flushToStorage($basename = '');

}
